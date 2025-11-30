<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function __construct()
    {
        // All cart routes require authentication
        $this->middleware('auth:api');
    }

    /**
     * Add product to cart (authenticated user).
     */
    public function addToCart(Request $request)
    {
        try {
            $validated = $request->validate([
                'product_id' => 'required|integer|exists:products,id',
                'quantity' => 'required|integer|min:1',
            ]);

            // Get authenticated user from JWT token
            $user = auth()->user();

            DB::beginTransaction();

            // Get product
            $product = Product::find($validated['product_id']);

            if (!$product->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product is not available'
                ], 400);
            }

            // Check stock availability
            if ($product->stock < $validated['quantity']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient stock available. Only ' . $product->stock . ' items in stock.'
                ], 400);
            }

            // Get or create active cart for authenticated user
            $cart = Cart::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'status' => 'active'
                ],
                [
                    'total_amount' => 0
                ]
            );

            // Check if product already exists in cart
            $cartItem = CartItem::where('cart_id', $cart->id)
                ->where('product_id', $validated['product_id'])
                ->first();

            if ($cartItem) {
                // Update existing cart item
                $newQuantity = $cartItem->quantity + $validated['quantity'];
                
                // Check stock for new quantity
                if ($product->stock < $newQuantity) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient stock available. Only ' . $product->stock . ' items in stock. You already have ' . $cartItem->quantity . ' in cart.'
                    ], 400);
                }

                $cartItem->quantity = $newQuantity;
                $cartItem->line_total = $newQuantity * $product->price;
                $cartItem->save();

                $message = 'Product quantity updated in cart successfully';
            } else {
                // Create new cart item
                $cartItem = CartItem::create([
                    'cart_id' => $cart->id,
                    'product_id' => $validated['product_id'],
                    'user_id' => $user->id,
                    'quantity' => $validated['quantity'],
                    'unit_price' => $product->price,
                    'line_total' => $validated['quantity'] * $product->price,
                ]);

                $message = 'Product added to cart successfully';
            }

            // Update cart total
            $cart->total_amount = CartItem::where('cart_id', $cart->id)
                ->sum('line_total');
            $cart->save();

            // Load product relationship for response
            $cartItem->load('product');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'cart' => $cart,
                    'cart_item' => $cartItem
                ]
            ], 201);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to add product to cart',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get authenticated user's active cart with items.
     */
    public function getCart()
    {
        try {
            // Get authenticated user from JWT token
            $user = auth()->user();

            $cart = Cart::with(['items.product'])
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->first();

            if (!$cart) {
                return response()->json([
                    'success' => true,
                    'message' => 'Your cart is empty',
                    'data' => [
                        'cart' => null,
                        'items' => [],
                        'total_items' => 0,
                        'total_amount' => '0.00'
                    ]
                ], 200);
            }

            $totalItems = $cart->items->sum('quantity');

            return response()->json([
                'success' => true,
                'data' => [
                    'cart' => $cart,
                    'items' => $cart->items,
                    'total_items' => $totalItems,
                    'total_amount' => $cart->total_amount
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve cart',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update cart item quantity.
     */
    public function updateCartItem(Request $request, $itemId)
    {
        try {
            $validated = $request->validate([
                'quantity' => 'required|integer|min:1',
            ]);

            $user = auth()->user();

            DB::beginTransaction();

            $cartItem = CartItem::where('id', $itemId)
                ->where('user_id', $user->id)
                ->first();

            if (!$cartItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cart item not found'
                ], 404);
            }

            $product = Product::find($cartItem->product_id);

            // Check stock availability
            if ($product->stock < $validated['quantity']) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient stock available. Only ' . $product->stock . ' items in stock.'
                ], 400);
            }

            // Update cart item
            $cartItem->quantity = $validated['quantity'];
            $cartItem->line_total = $validated['quantity'] * $cartItem->unit_price;
            $cartItem->save();

            // Update cart total
            $cart = Cart::find($cartItem->cart_id);
            $cart->total_amount = CartItem::where('cart_id', $cart->id)
                ->sum('line_total');
            $cart->save();

            DB::commit();

            $cartItem->load('product');

            return response()->json([
                'success' => true,
                'message' => 'Cart item updated successfully',
                'data' => [
                    'cart' => $cart,
                    'cart_item' => $cartItem
                ]
            ], 200);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update cart item',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove item from cart.
     */
    public function removeCartItem($itemId)
    {
        try {
            $user = auth()->user();

            DB::beginTransaction();

            $cartItem = CartItem::where('id', $itemId)
                ->where('user_id', $user->id)
                ->first();

            if (!$cartItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cart item not found'
                ], 404);
            }

            $cartId = $cartItem->cart_id;
            $cartItem->delete();

            // Update cart total
            $cart = Cart::find($cartId);
            if ($cart) {
                $cart->total_amount = CartItem::where('cart_id', $cart->id)
                    ->sum('line_total');
                $cart->save();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Item removed from cart successfully',
                'data' => [
                    'cart' => $cart
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove cart item',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear all items from cart.
     */
    public function clearCart()
    {
        try {
            $user = auth()->user();

            DB::beginTransaction();

            $cart = Cart::where('user_id', $user->id)
                ->where('status', 'active')
                ->first();

            if (!$cart) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active cart found'
                ], 404);
            }

            CartItem::where('cart_id', $cart->id)->delete();

            $cart->total_amount = 0;
            $cart->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cart cleared successfully',
                'data' => [
                    'cart' => $cart
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cart',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get cart items count.
     */
    public function getCartCount()
    {
        try {
            $user = auth()->user();

            $cart = Cart::where('user_id', $user->id)
                ->where('status', 'active')
                ->first();

            if (!$cart) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'count' => 0,
                        'total_items' => 0
                    ]
                ], 200);
            }

            $totalItems = CartItem::where('cart_id', $cart->id)->sum('quantity');
            $itemCount = CartItem::where('cart_id', $cart->id)->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'count' => $itemCount,
                    'total_items' => $totalItems
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get cart count',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}