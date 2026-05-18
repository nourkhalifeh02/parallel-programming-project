<?php

namespace Database\Seeders;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'wallet' => 10000.00,
        ]);

        User::create([
            'name' => 'Customer',
            'email' => 'customer@example.com',
            'password' => Hash::make('password'),
            'role' => 'customer',
            'wallet' => 10000.00,
        ]);

        User::create([
            'name' => 'Test User 3',
            'email' => 'testuser3@example.com',
            'password' => Hash::make('password'),
            'role' => 'customer',
            'wallet' => 100000000.00,
        ]);

        for ($i = 1; $i <= 100; $i++) {
            if ($i <= 20) {
                User::create([
                    'name' => "John Doe {$i}",
                    'email' => "johndoe{$i}@gmail.com",
                    'password' => Hash::make('123123'),
                    'role' => 'customer',
                    'wallet' => 10000.00,
                    'is_vip' => true,
                ]);
            } else {
                User::create([
                    'name' => "John Doe {$i}",
                    'email' => "johndoe{$i}@gmail.com",
                    'password' => Hash::make('123123'),
                    'role' => 'customer',
                    'wallet' => 10000.00,
                ]);
            }

        }

        Product::create([
            'name' => 'Laptop',
            'description' => 'High-performance laptop',
            'price' => 699.00,
            'inventory' => 1000000,
        ]);

        Product::create([
            'name' => 'Smartphone',
            'description' => 'Latest smartphone model',
            'price' => 699.99,
            'inventory' => 100,
        ]);

        Product::create([
            'name' => 'Headphones',
            'description' => 'Wireless noise-canceling headphones',
            'price' => 199.99,
            'inventory' => 200,
        ]);

        for ($i = 3; $i <= 102; $i++) {
            Cart::create([
                'user_id' => "{$i}",
                'product_id' => '1',
                'quantity' => 3,
            ]);
        }

        // for ($i = 1; $i <= 100000; $i++) {
        //     Order::create([
        //         'user_id' => 1,
        //         'total' => 699.99,
        //         'status' => 'completed',
        //     ]);

        //     OrderItem::create([
        //         'order_id' => "{$i}",
        //         'product_id' => 1,
        //         'quantity' => 1,
        //         'price' => 699.99,
        //     ]);
        // }

    }
}
