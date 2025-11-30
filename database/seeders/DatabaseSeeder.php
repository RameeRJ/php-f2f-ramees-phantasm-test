<?php

namespace Database\Seeders;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'buyer@example.com'],
            [
                'name' => 'Demo Buyer',
                'password' => Hash::make('password'),
            ]
        );

        $categories = collect([
            [
                'name' => 'Electronics',
                'slug' => 'electronics',
                'description' => 'Consumer electronics and gadgets.',
            ],
            [
                'name' => 'Wearables',
                'slug' => 'wearables',
                'description' => 'Smart watches and health trackers.',
            ],
            [
                'name' => 'Home Office',
                'slug' => 'home-office',
                'description' => 'Essentials for productive workspaces.',
            ],
        ])->mapWithKeys(function (array $data) {
            $category = Category::updateOrCreate(
                ['slug' => $data['slug']],
                $data
            );

            return [$category->slug => $category];
        });

        $products = collect([
            [
                'name' => 'Wireless Headphones',
                'slug' => 'wireless-headphones',
                'sku' => 'HEAD-001',
                'description' => 'Noise-cancelling, over-ear wireless headphones.',
                'price' => 199.99,
                'stock' => 50,
                'is_active' => true,
            ],
            [
                'name' => 'Smart Watch',
                'slug' => 'smart-watch',
                'sku' => 'WEAR-010',
                'description' => 'Fitness-focused smart watch with GPS.',
                'price' => 149.50,
                'stock' => 75,
                'is_active' => true,
            ],
            [
                'name' => 'Standing Desk',
                'slug' => 'standing-desk',
                'sku' => 'HOME-100',
                'description' => 'Adjustable standing desk with memory presets.',
                'price' => 499.00,
                'stock' => 15,
                'is_active' => true,
            ],
        ])->mapWithKeys(function (array $data) {
            $product = Product::updateOrCreate(
                ['slug' => $data['slug']],
                $data
            );

            return [$product->slug => $product];
        });

        $cart = Cart::updateOrCreate(
            [
                'user_id' => $user->id,
                'status' => 'open',
            ],
            [
                'total_amount' => 0,
                'checked_out_at' => null,
            ]
        );

        CartItem::where('cart_id', $cart->id)->delete();

        $cartItems = [
            ['product_slug' => 'wireless-headphones', 'quantity' => 1],
            ['product_slug' => 'smart-watch', 'quantity' => 2],
        ];

        $totalAmount = 0;

        foreach ($cartItems as $item) {
            $product = $products[$item['product_slug']] ?? null;

            if (!$product) {
                continue;
            }

            $unitPrice = $product->price;
            $lineTotal = $unitPrice * $item['quantity'];

            CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'user_id' => $user->id,
                'quantity' => $item['quantity'],
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
            ]);

            $totalAmount += $lineTotal;
        }

        $cart->update(['total_amount' => $totalAmount]);
    }
}
