<?php

namespace App\Http\Controllers;

use App\Jobs\CreateInvoice;
use App\Jobs\OrderCompletedNotification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = $request->user()->orders()->with('items.product')->get();

        return response()->json($orders);
    }

    public function show(Request $request, Order $order)
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($order->load('items.product'));
    }

    private function getOrCacheProduct(int $productId): array
    {
        $cacheKey = 'product:'.$productId;
        $product = Cache::get($cacheKey);

        if ($product === null) {
            $dbProduct = Product::findOrFail($productId);
            $product = $dbProduct->toArray();
            Cache::put($cacheKey, $product, now()->addMinutes(10));
        } else {
            Cache::put($cacheKey, $product, now()->addMinutes(10));
        }

        return $product;
    }

    private function updateProductInCache(int $productId, array $product): void
    {
        $cacheKey = 'product:'.$productId;
        Cache::put($cacheKey, $product, now()->addMinutes(10));
        Redis::connection('cache')->sadd('products:sync_set', $productId);
    }

    public function orderCartRedis(Request $request)
    {
        $user = $request->user();
        $carts = $user->carts()->with('product')->get();

        if ($carts->isEmpty()) {
            return response()->json(['message' => 'Cart is empty'], 400);
        }

        $productIds = $carts->pluck('product_id')->unique();
        $allLockKeys = $productIds
            ->map(fn ($id) => "pessimistic:product:{$id}")
            ->push("pessimistic:user:{$user->id}")
            ->sort()
            ->values()
            ->all();

        $locks = [];
        try {
            foreach ($allLockKeys as $key) {
                $lock = Cache::lock($key, 30);
                try {
                    $lock->block(10);
                } catch (LockTimeoutException $e) {
                    throw new \RuntimeException("Timed out waiting for lock: {$key}");
                }
                $locks[] = $lock;
            }

            $cachedProducts = [];
            foreach ($productIds as $productId) {
                $cachedProducts[$productId] = $this->getOrCacheProduct($productId);
            }

            foreach ($carts as $cart) {
                $product = $cachedProducts[$cart->product_id];
                if ($product['inventory'] < $cart->quantity) {
                    throw new \DomainException("Insufficient inventory for {$product['name']}");
                }
            }

            $total = $carts->sum(
                fn ($item) => $item->quantity * $cachedProducts[$item->product_id]['price']
            );

            $result = DB::transaction(function () use ($user, $carts, $total, $cachedProducts) {
                $freshUser = User::findOrFail($user->id);

                if ($freshUser->wallet < $total) {
                    throw new \DomainException('Insufficient wallet balance');
                }

                $order = Order::create([
                    'user_id' => $freshUser->id,
                    'total' => $total,
                    'status' => 'completed',
                ]);

                foreach ($carts as $cart) {
                    $product = $cachedProducts[$cart->product_id];
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $cart->product_id,
                        'quantity' => $cart->quantity,
                        'price' => $product['price'],
                    ]);

                    $product['inventory'] -= $cart->quantity;
                    $this->updateProductInCache($cart->product_id, $product);
                }

                $freshUser->decrement('wallet', $total);
                $user->carts()->delete();

                return ['order' => $order, 'userId' => $freshUser->id, 'total' => $total];
            });

        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 400);

        } catch (\RuntimeException $e) {
            return response()->json(['message' => 'Order could not be completed. Please try again.'], 503);

        } finally {
            foreach (array_reverse($locks) as $lock) {
                $lock->release();
            }
        }

        OrderCompletedNotification::dispatchSync($result['userId'], (float) $result['total']);
        CreateInvoice::dispatchSync($result['userId'], (float) $result['total']);

        return response()->json([
            'message' => 'Order placed successfully',
            'order' => $result['order']->load('items.product'),
        ], 201);
    }

    public function orderProductRedis(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $user = $request->user();
        $maxRetries = 3;
        $productId = $validated['product_id'];

        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            $product = $this->getOrCacheProduct($productId);

            if ($product['inventory'] < $validated['quantity']) {
                return response()->json(['message' => 'Insufficient inventory'], 400);
            }

            $total = $product['price'] * $validated['quantity'];

            if ($user->wallet < $total) {
                return response()->json(['message' => 'Insufficient wallet balance'], 400);
            }

            $snapshotVersion = $product['version'] ?? 0;

            $casLockKey = "lock:optimistic:product:{$productId}";
            $casLock = Cache::lock($casLockKey, 5);
            $casSucceeded = false;

            if ($casLock->get()) {
                try {
                    $currentProduct = Cache::get('product:'.$productId);
                    if ($currentProduct !== null && ($currentProduct['version'] ?? 0) === $snapshotVersion) {
                        if ($currentProduct['inventory'] >= $validated['quantity']) {
                            $currentProduct['inventory'] -= $validated['quantity'];
                            $currentProduct['version'] = ($currentProduct['version'] ?? 0) + 1;
                            $this->updateProductInCache($productId, $currentProduct);
                            $casSucceeded = true;
                        }
                    }
                } finally {
                    $casLock->release();
                }
            }

            if (! $casSucceeded) {
                continue; 
            }

            try {
                $result = DB::transaction(function () use ($user, $product, $validated, $total) {
                    $lockedUser = User::where('id', $user->id)->lockForUpdate()->first();

                    if ($lockedUser->wallet < $total) {
                        throw new \DomainException('Insufficient wallet balance');
                    }

                    $order = Order::create([
                        'user_id' => $user->id,
                        'total' => $total,
                        'status' => 'completed',
                    ]);

                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $product['id'],
                        'quantity' => $validated['quantity'],
                        'price' => $product['price'],
                    ]);

                    $lockedUser->decrement('wallet', $total);

                    return ['order' => $order, 'userId' => $lockedUser->id, 'total' => $total];
                });

                OrderCompletedNotification::dispatchSync($result['userId'], (float) $result['total']);
                CreateInvoice::dispatchSync($result['userId'], (float) $result['total']);

                return response()->json([
                    'message' => 'Order placed successfully',
                    'order' => $result['order']->load('items.product'),
                ], 201);

            } catch (\DomainException $e) {
                $casLock = Cache::lock($casLockKey, 5);
                if ($casLock->get()) {
                    try {
                        $currentProduct = Cache::get('product:'.$productId);
                        if ($currentProduct !== null) {
                            $currentProduct['inventory'] += $validated['quantity'];
                            if (($currentProduct['version'] ?? 0) === $snapshotVersion + 1) {
                                $currentProduct['version'] = $snapshotVersion;
                            }
                            $this->updateProductInCache($productId, $currentProduct);
                        }
                    } finally {
                        $casLock->release();
                    }
                }

                return response()->json(['message' => $e->getMessage()], 400);

            } catch (\Exception $e) {
                $casLock = Cache::lock($casLockKey, 5);
                if ($casLock->get()) {
                    try {
                        $currentProduct = Cache::get('product:'.$productId);
                        if ($currentProduct !== null) {
                            $currentProduct['inventory'] += $validated['quantity'];
                            if (($currentProduct['version'] ?? 0) === $snapshotVersion + 1) {
                                $currentProduct['version'] = $snapshotVersion;
                            }
                            $this->updateProductInCache($productId, $currentProduct);
                        }
                    } finally {
                        $casLock->release();
                    }
                }
                throw $e;
            }
        }

        return response()->json([
            'message' => 'Order could not be completed due to high demand. Please try again.',
        ], 409);
    }

    public function orderCart(Request $request)
{
    $user = $request->user();
    $carts = $user->carts()->with('product')->get();

    if ($carts->isEmpty()) {
        return response()->json(['message' => 'Cart is empty'], 400);
    }

    $result = DB::transaction(function () use ($user, $carts) {
        $productIds = $carts->pluck('product_id');
        $lockedProducts = Product::whereIn('id', $productIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($carts as $cart) {
            $product = $lockedProducts->get($cart->product_id);
            if ($product->inventory < $cart->quantity) {

                throw new \DomainException("Insufficient inventory for {$product->name}");
            }
        }

        $total = $carts->sum(
            fn($item) => $item->quantity * $lockedProducts->get($item->product_id)->price
        );

        $lockedUser = User::where('id', $user->id)->lockForUpdate()->first();

        if ($lockedUser->wallet < $total) {
            throw new \DomainException('Insufficient wallet balance');
        }

        $order = Order::create([
            'user_id' => $lockedUser->id,
            'total'   => $total,
            'status'  => 'completed',
        ]);

        foreach ($carts as $cart) {
            $product = $lockedProducts->get($cart->product_id);
            OrderItem::create([
                'order_id'   => $order->id,
                'product_id' => $product->id,
                'quantity'   => $cart->quantity,
                'price'      => $product->price,
            ]);
            $product->decrement('inventory', $cart->quantity);
        }

        $lockedUser->decrement('wallet', $total);
        $user->carts()->delete();

        return ['order' => $order, 'userId' => $lockedUser->id, 'total' => $total];
    });

    OrderCompletedNotification::dispatchSync($result['userId'], (float) $result['total']);
    CreateInvoice::dispatchSync($result['userId'], (float) $result['total']);

    return response()->json([
        'message' => 'Order placed successfully',
        'order'   => $result['order']->load('items.product'),
    ], 201);
}

    public function orderProduct(Request $request)
{
    $validated = $request->validate([
        'product_id' => 'required|exists:products,id',
        'quantity'   => 'required|integer|min:1',
    ]);

    $user = $request->user();
    $maxRetries = 3;

    for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
        $product = Product::findOrFail($validated['product_id']);

        if ($product->inventory < $validated['quantity']) {
            return response()->json(['message' => 'Insufficient inventory'], 400);
        }

        $total = $product->price * $validated['quantity'];

        if ($user->wallet < $total) {
            return response()->json(['message' => 'Insufficient wallet balance'], 400);
        }

        try {
            $result = DB::transaction(function () use ($user, $product, $validated, $total) {
                $affected = Product::where('id', $product->id)
                    ->where('version', $product->version)
                    ->where('inventory', '>=', $validated['quantity'])
                    ->update([
                        'inventory' => DB::raw("inventory - {$validated['quantity']}"),
                        'version'   => DB::raw('version + 1'),
                    ]);

                if ($affected === 0) {
                    throw new \RuntimeException('optimistic_lock_failure');
                }

                $lockedUser = User::where('id', $user->id)->lockForUpdate()->first();

                if ($lockedUser->wallet < $total) {
                    throw new \DomainException('Insufficient wallet balance');
                }

                $order = Order::create([
                    'user_id' => $user->id,
                    'total'   => $total,
                    'status'  => 'completed',
                ]);

                OrderItem::create([
                    'order_id'   => $order->id,
                    'product_id' => $product->id,
                    'quantity'   => $validated['quantity'],
                    'price'      => $product->price,
                ]);

                $lockedUser->decrement('wallet', $total);

                return ['order' => $order, 'userId' => $lockedUser->id, 'total' => $total];
            });

            OrderCompletedNotification::dispatchSync($result['userId'], (float) $result['total']);
            CreateInvoice::dispatchSync($result['userId'], (float) $result['total']);

            return response()->json([
                'message' => 'Order placed successfully',
                'order'   => $result['order']->load('items.product'),
            ], 201);

        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 400);

        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'optimistic_lock_failure') {
                continue; 
            }
            throw $e;
        }
    }

    return response()->json([
        'message' => 'Order could not be completed due to high demand. Please try again.',
    ], 409);
}

    public function ordercartsequential(Request $request)
    {
        $user = $request->user();
        $carts = $user->carts()->get();

        if ($carts->isEmpty()) {
            return response()->json(['message' => 'Cart is empty'], 400);
        }

        try {
            $result = DB::transaction(function () use ($user, $carts) {
                $total = 0;
                $productsData = [];

                foreach ($carts as $cart) {
                    $product = Product::where('id', $cart->product_id)->lockForUpdate()->firstOrFail();
                    if ($product->inventory < $cart->quantity) {
                        throw new \DomainException("Insufficient inventory for {$product->name}");
                    }

                    $total += $cart->quantity * $product->price;
                    $product->decrement('inventory', $cart->quantity);

                    $productsData[] = [
                        'id' => $product->id,
                        'quantity' => $cart->quantity,
                        'price' => $product->price,
                    ];
                }

                $order = Order::create([
                    'user_id' => $user->id,
                    'total' => $total,
                    'status' => 'completed',
                ]);

                foreach ($productsData as $data) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $data['id'],
                        'quantity' => $data['quantity'],
                        'price' => $data['price'],
                    ]);
                }

                $user->carts()->delete();

                return ['order' => $order, 'userId' => $user->id, 'total' => $total];
            });

            OrderCompletedNotification::dispatchSync($result['userId'], (float) $result['total']);
            CreateInvoice::dispatchSync($result['userId'], (float) $result['total']);

            return response()->json([
                'message' => 'Order placed successfully (sequential)',
                'order' => $result['order']->load('items.product'),
            ], 201);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function ordercartenhanced(Request $request)
    {
        $user = $request->user();
        $carts = $user->carts()->get();

        if ($carts->isEmpty()) {
            return response()->json(['message' => 'Cart is empty'], 400);
        }

        $productIds = $carts->pluck('product_id')->toArray();

        try {
            $result = DB::transaction(function () use ($user, $carts, $productIds) {
                $products = Product::whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id');

                $total = 0;
                $orderItemsData = [];

                foreach ($carts as $cart) {
                    $product = $products->get($cart->product_id);
                    if (! $product || $product->inventory < $cart->quantity) {
                        throw new \DomainException('Insufficient inventory for '.($product->name ?? 'product'));
                    }

                    $total += $cart->quantity * $product->price;
                    $orderItemsData[] = [
                        'product_id' => $product->id,
                        'quantity' => $cart->quantity,
                        'price' => $product->price,
                    ];

                    $product->decrement('inventory', $cart->quantity);
                }

                $order = Order::create([
                    'user_id' => $user->id,
                    'total' => $total,
                    'status' => 'completed',
                ]);

                foreach ($orderItemsData as $item) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                    ]);
                }

                $user->carts()->delete();

                return ['order' => $order, 'userId' => $user->id, 'total' => $total];
            });

            OrderCompletedNotification::dispatchSync($result['userId'], (float) $result['total']);
            CreateInvoice::dispatchSync($result['userId'], (float) $result['total']);

            return response()->json([
                'message' => 'Order placed successfully (enhanced)',
                'order' => $result['order']->load('items.product'),
            ], 201);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}
