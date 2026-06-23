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

    $products = [
    ['name' => 'Laptop',              'description' => 'High-performance laptop',              'price' => 699.00,  'inventory' => 100],
    ['name' => 'Smartphone',          'description' => 'Latest smartphone model',              'price' => 399.99,  'inventory' => 71],
    ['name' => 'Headphones',          'description' => 'Wireless noise-canceling headphones',  'price' => 199.99,  'inventory' => 23],
    // ['name' => 'Tablet',              'description' => '10-inch Android tablet',               'price' => 349.99,  'inventory' => 55],
    // ['name' => 'Smartwatch',          'description' => 'Fitness tracking smartwatch',          'price' => 249.99,  'inventory' => 40],
    // ['name' => 'Monitor',             'description' => '27-inch 4K UHD monitor',               'price' => 429.99,  'inventory' => 30],
    // ['name' => 'Keyboard',            'description' => 'Mechanical gaming keyboard',           'price' => 89.99,   'inventory' => 120],
    // ['name' => 'Mouse',               'description' => 'Ergonomic wireless mouse',             'price' => 49.99,   'inventory' => 150],
    // ['name' => 'Webcam',              'description' => '1080p HD webcam with microphone',      'price' => 79.99,   'inventory' => 65],
    // ['name' => 'Speaker',             'description' => 'Portable Bluetooth speaker',           'price' => 129.99,  'inventory' => 80],
    // ['name' => 'USB Hub',             'description' => '7-port USB 3.0 hub',                  'price' => 34.99,   'inventory' => 200],
    // ['name' => 'SSD',                 'description' => '1TB external solid state drive',       'price' => 109.99,  'inventory' => 90],
    // ['name' => 'Graphics Card',       'description' => 'High-end GPU for gaming',              'price' => 599.99,  'inventory' => 15],
    // ['name' => 'RAM',                 'description' => '16GB DDR5 desktop memory',             'price' => 74.99,   'inventory' => 110],
    // ['name' => 'Router',              'description' => 'Wi-Fi 6 dual-band router',             'price' => 159.99,  'inventory' => 45],
    // ['name' => 'Printer',             'description' => 'Wireless all-in-one inkjet printer',   'price' => 189.99,  'inventory' => 28],
    // ['name' => 'Desk Lamp',           'description' => 'LED smart desk lamp with USB port',    'price' => 39.99,   'inventory' => 175],
    // ['name' => 'Camera',              'description' => 'Mirrorless digital camera 24MP',       'price' => 849.99,  'inventory' => 18],
    // ['name' => 'Drone',               'description' => 'Foldable drone with 4K camera',        'price' => 499.99,  'inventory' => 12],
    // ['name' => 'VR Headset',          'description' => 'Standalone virtual reality headset',   'price' => 399.99,  'inventory' => 22],
    // ['name' => 'Game Controller',     'description' => 'Wireless controller for PC and console','price' => 59.99,  'inventory' => 95],
    // ['name' => 'Power Bank',          'description' => '20000mAh fast-charge power bank',      'price' => 44.99,   'inventory' => 130],
    // ['name' => 'Cable Management',    'description' => 'Under-desk cable management kit',      'price' => 19.99,   'inventory' => 300],
    // ['name' => 'Laptop Stand',        'description' => 'Adjustable aluminum laptop stand',     'price' => 29.99,   'inventory' => 160],
    // ['name' => 'Microphone',          'description' => 'USB condenser microphone for streaming','price' => 99.99,  'inventory' => 50],
    ];

    Product::insert($products);

        for ($i = 3; $i <= 102; $i++) {
            for ($j = 1; $j <= 1; $j++) {
                Cart::create([
                    'user_id' => "{$i}",
                    'product_id' => "{$j}",
                    'quantity' => 3,
                ]);
            }
        }

        // for ($i = 1; $i <= 10; $i++) {
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
