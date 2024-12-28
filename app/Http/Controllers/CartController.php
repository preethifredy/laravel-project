<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Surfsidemedia\Shoppingcart\Facades\Cart;
use App\Models\Coupon;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;


class CartController extends Controller
{
    public function index()
    {
        $cartItems = Cart::instance('cart')->content();
        return view('cart',compact('cartItems'));
    }

    public function addToCart(Request $request)
    {
        Cart::instance('cart')->add($request->id,$request->name,$request->quantity,$request->price)->associate('App\Models\Product');        
        session()->flash('success', 'Product is Added to Cart Successfully !');        
        return redirect()->back();
        //return response()->json(['status'=>200,'message'=>'Success ! Item Successfully added to your cart.']);
    } 

    public function increase_item_quantity($rowId)
    {
        $product = Cart::instance('cart')->get($rowId);
        $qty = $product->qty + 1;
        Cart::instance('cart')->update($rowId,$qty);
        return redirect()->back();
    }

    public function reduce_item_quantity($rowId){
        $product = Cart::instance('cart')->get($rowId);
        $qty = $product->qty - 1;
        Cart::instance('cart')->update($rowId,$qty);
        return redirect()->back();
    }

    public function remove_item_from_cart($rowId)
    {
        Cart::instance('cart')->remove($rowId);
        return redirect()->back();
    }

    public function empty_cart()
    {
        Cart::instance('cart')->destroy();
        return redirect()->back();
    }

    public function apply_coupon_code(Request $request)
    {       
        DB::enableQueryLog(); 
        $coupon_code = $request->coupon_code;
        if(isset($coupon_code))
        {
             $coupon = Coupon::where('code',$coupon_code)->where('expiry_date','>=',Carbon::today())->where('cart_value','<=',str_replace(',','',Cart::instance('cart')->subtotal()))->first();
             //dd(DB::getQueryLog());
            if(!$coupon)
            {
                return back()->with('error','Invalid coupon code!'.Cart::instance('cart')->subtotal());
            }
            session()->put('coupon',[
                'code' => $coupon->code,
                'type' => $coupon->type,
                'value' => $coupon->value,
                'cart_value' => $coupon->cart_value
            ]);
            $this->calculateDiscounts();
            return back()->with('status','Coupon code has been applied!');
        }
        else{
            return back()->with('error','Invalid coupon code!');
        }        
    }

    public function calculateDiscounts()
    {
        $discount = 0;
        if(session()->has('coupon'))
        {
            if(session()->get('coupon')['type'] == 'fixed')
            {
                $discount = session()->get('coupon')['value'];
            }
            else
            {
                $discount = (str_replace(',','',Cart::instance('cart')->subtotal()) * session()->get('coupon')['value'])/100;
            }

            $subtotalAfterDiscount = str_replace(',','',Cart::instance('cart')->subtotal()) - $discount;
            $taxAfterDiscount = ($subtotalAfterDiscount * config('cart.tax'))/100;
            $totalAfterDiscount = $subtotalAfterDiscount + $taxAfterDiscount; 

            session()->put('discounts',[
                'discount' => number_format(floatval($discount),2,'.',''),
                'subtotal' => number_format(floatval(str_replace(',','',Cart::instance('cart')->subtotal()) - $discount),2,'.',''),
                'tax' => number_format(floatval((($subtotalAfterDiscount * config('cart.tax'))/100)),2,'.',''),
                'total' => number_format(floatval($subtotalAfterDiscount + $taxAfterDiscount),2,'.','')
            ]);            
        }
    }

    public function remove_coupon_code()
    {
        session()->forget('coupon');
        session()->forget('discounts');
        return back()->with('status','Coupon has been removed!');
    }

}
