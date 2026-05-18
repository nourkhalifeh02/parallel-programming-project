<?php

namespace App\Http\Controllers;

use App\Jobs\CreateInvoice;
use App\Jobs\OrderCompletedNotification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function orderCart(Request $request)
    {
        $user = $request->user();
        $carts = $user->carts()->with('product')->get();

        if ($carts->isEmpty()) {
            return response()->json(['message' => 'Cart is empty'], 400);
        }

        foreach ($carts as $cart) {
            if ($cart->product->inventory < $cart->quantity) {
                return response()->json([
                    'message' => "Insufficient inventory for {$cart->product->name}",
                ], 400);
            }
        }

        $total = $carts->sum(function ($item) {
            return $item->quantity * $item->product->price;
        });

        if ($user->wallet < $total) {
            return response()->json(['message' => 'Insufficient wallet balance'], 400);
        }
        //DB::transaction create a pessimistic lock
        return DB::transaction(function () use ($user, $carts, $total) {
            $order = Order::create([
                'user_id' => $user->id,
                'total' => $total,
                'status' => 'completed',
            ]);

            foreach ($carts as $cart) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $cart->product_id,
                    'quantity' => $cart->quantity,
                    'price' => $cart->product->price,
                ]);

                $cart->product->decrement('inventory', $cart->quantity);
            }

            $user->decrement('wallet', $total);
            $user->carts()->delete();

            OrderCompletedNotification::dispatchSync($user->id, (float) $total);
            CreateInvoice::dispatchSync($user->id, (float) $total);

            return response()->json(['message' => 'Order placed successfully', 'order' => $order->load('items.product')], 201);
        });
    }

    public function orderProduct(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $user = $request->user();
        $product = Product::findOrFail($validated['product_id']);

        if ($product->inventory < $validated['quantity']) {
            return response()->json(['message' => 'Insufficient inventory'], 400);
        }

        $total = $product->price * $validated['quantity'];

        if ($user->wallet < $total) {
            return response()->json(['message' => 'Insufficient wallet balance'], 400);
        }

        return DB::transaction(function () use ($user, $product, $validated, $total) {
            $order = Order::create([
                'user_id' => $user->id,
                'total' => $total,
                'status' => 'completed',
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => $validated['quantity'],
                'price' => $product->price,
            ]);

            $product->decrement('inventory', $validated['quantity']);
            $user->decrement('wallet', $total);

            OrderCompletedNotification::dispatchSync($user->id, (float) $total);
            CreateInvoice::dispatchSync($user->id, (float) $total);

            return response()->json(['message' => 'Order placed successfully', 'order' => $order->load('items.product')], 201);
        });
    }
}
