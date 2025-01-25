<?php

namespace Modules\Mutation\Http\Controllers;

use Modules\Mutation\DataTables\MutationsDataTable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\People\Entities\Supplier;
use Modules\People\Entities\Customer;
use App\Models\AccountingAccount;
use App\Helpers\Helper;
use Modules\Product\Entities\Product;
use App\Models\AccountingTransaction;
use Modules\Mutation\Entities\Mutation;
use Modules\Sale\Entities\Sale;
use Modules\Sale\Entities\SaleDetails;
use Modules\Sale\Entities\SalePayment;
use Modules\Purchase\Entities\Purchase;
use Carbon\Carbon;
use Modules\Purchase\Entities\PurchaseDetail;
use Modules\Purchase\Entities\PurchasePayment;
use Modules\Product\Notifications\NotifyQuantityAlert;
class Debit {
    // Properties
    public $tanggal;
    public $nominal;
    public $keterangan;
}
class Credit {
    // Properties
    public $tanggal;
    public $nominal;
    public $keterangan;
}
class MutationController extends Controller
{

    public function index(MutationsDataTable $dataTable) {
        abort_if(Gate::denies('access_mutations'), 403);

        return $dataTable->render('mutation::index');
    }


    public function create() {
        abort_if(Gate::denies('create_mutations'), 403);

        // $test = Product::
        // $test = Mutation::with('product')
        // ->latest()
        // ->get()
        // ->unique('product_id');
        // // ->sortByDesc('stock_last');
        // // dd($test[0]);
        // foreach($test as $pos){
        //     // dd($pos->stock_last);
        //     $product = Product::where('id', $pos->product_id )->first();
        //     $product->update([
        //         'product_quantity' => $pos->stock_last
        //     ]);
        // }

        // $test = Purchase::get();

        // foreach($test as $pos){
        //     if($pos->payment_status == 'Paid'){
        //         $created_payment = PurchasePayment::create([
        //             'date' => $ks[2],
        //             'reference' => $purchase->reference,
        //             'amount' => $purchase->paid_amount,
        //             'note' => 'Payment',
        //             'purchase_id' => $purchase->id,
        //             'payment_method' => '1-10002'
        //         ]);
        //         Helper::addNewTransaction([
        //              'date'=> $ks[2],
        //              'label' => "Payment for Purchase Order #".$purchase->reference,
        //              'description' => "Purchase ID: ".$purchase->reference,
        //              'purchase_id' => null,
        //              'purchase_payment_id' => $created_payment->id,
        //              'purchase_return_id' => null,
        //              'purchase_return_payment_id' => null,
        //              'sale_id' => null,
        //              'sale_payment_id' => null,
        //              'sale_return_id' => null,
        //              'sale_return_payment_id' => null,
        //          ], [
        //              [
        //                  'subaccount_number' => '1-10200', // Persediaan dalam pengiriman
        //                  'amount' => $created_payment->amount,
        //                  'type' => 'debit'
        //              ],
        //              [
        //                  'subaccount_number' => $created_payment->payment_method, // Kas
        //                  'amount' => $created_payment->amount,
        //                  'type' => 'credit'
        //              ]
        //          ]);
        //     }
        // }

        
        // ->sum('stock_last');
        // dd($test[0]);
        // $weeks = Timesheet::where('week_ending', $request->week_ending)->get();
        // $week_ending = Carbon::parse($request->week_ending)->format('d-m-Y');
        // $html = '';
        // foreach($weeks as $hours)
        // {
        //     $view = view('pdf.timesheet')->with(compact('hours', 'week_ending'));
        //     $html .= $view->render();
        // }
        // $pdf = PDF::loadHTML($html);            
        // $sheet = $pdf->setPaper('a4', 'landscape');
        // return $sheet->download('download.pdf');

        // Download All PDF POS
        // ini_set('max_execution_time',3600);
        // $sales = \Modules\Sale\Entities\Sale::whereMonth('date','=','10')->get();
        // foreach($sales as $index=>$sale){
        //     $customer = \Modules\People\Entities\Customer::findOrFail($sale->customer_id);
        //     $html = '';
        //     $view =view('sale::print-pos', [
        //         'sale' => $sale,
        //         'customer' => $customer,
        //     ]);
        //     $html .= $view->render();
        //     \PDF::loadHTML($html)->save(public_path().'/oktober/'.$sale->reference.'.pdf');
        // }
        // ini_set('max_execution_time', 3600);
        // $sales = \Modules\Sale\Entities\Sale::whereMonth('date', '=', '12')->get();
        // foreach ($sales as $index => $sale) {
        //     $customer = \Modules\People\Entities\Customer::findOrFail($sale->customer_id);
        //     $html = '';
        //     $view = view('sale::print-pos', [
        //         'sale' => $sale,
        //         'customer' => $customer,
        //     ]);
        //     $html .= $view->render();

        //     // Load the HTML and customize the page
        //     $pdf = \PDF::loadHTML($html)
        //         ->setPaper('A4', 'portrait') // Set paper size and orientation ('A4', 'landscape' for horizontal)
        //         ->setOptions([
        //             'margin-top' => 10,
        //             'margin-right' => 4,
        //             'margin-bottom' => 10,
        //             'margin-left' => 4,
        //             'dpi' => 150,
        //             'zoom' => 1.0
        //         ]);

        //     // Save the file
        //     $pdf->save(public_path() . '/desember/' . $sale->note . '.pdf');
        //     // break;
        // }
        // $pdf = \PDF::loadHtml($html);
        // $sheet = $pdf->setPaper('a4');
        // return $sheet->download('sale.pdf');

        // penjualan header & detil
        // $csvFileName = "penj_b_des.csv";
        // $csvFile = public_path('csv/' . $csvFileName);
        // $konsyinasi = $this->readCSV($csvFile,array('delimiter' => ','));
        // $header_temp="";
        // $total_amount = 0;
        // $total_quantity = 0;
        // $total_hpp = 0;
        // dd($konsyinasi);
        // foreach($konsyinasi as $index=>$ks){
        //     $created_sale = Sale::where('note','=',$ks[0])->first();
        //     if($created_sale){
        //         $customer = Customer::where('customer_name', $ks[10])->first();
        //         // dd($customer);
        //         if(!$customer){
        //             $customer = Customer::create([
        //                 'customer_name' => $ks[10],
        //                 'customer_email'=> $ks[12],
        //                 'customer_phone'=> $ks[11],
        //                 'city' => $ks[14],
        //                 'address'=> $ks[14],
        //                 'country' => 'Indonesia'
        //             ]);
                  
        //         }
        //         $created_sale->update([
        //             'customer_id' => $customer->id,
        //             'customer_name' => $customer->customer_name,
        //         ]);
        //     }
        // }
        // dd($konsyinasi);
        // foreach($konsyinasi as $index=>$ks){
        //     // dd($index);
        //     // dd($ks[0]);
        //     // dd(Carbon::createFromFormat('d/m/Y', )->format('Y-m-d'));
        //     if($header_temp !== $ks[0] ){
        //         // if($index == array_key_last($konsyinasi)){
        //         //     dd($ks, $total_amount, $total_hpp, $total_quantity);
        //         // }
        //         if($total_amount > 0){
        //             // $header_temp = str_replace("\xEF\xBB\xBF", '', $header_temp);
        //             // $header_temp = trim(preg_replace('/[[:cntrl:]]/', '', $header_temp));
        //             // var_dump($header_temp, (string)$ks[0]);
        //             // dd($header_temp, $ks[0]);
        //             $created_sale = Sale::where('note','=',$header_temp)->first();
        //             // dd($created_sale);
        //             if($total_amount > $created_sale->total_amount){
        //                 $created_sale->update([
        //                     'paid_amount' => $total_amount,
        //                     'total_amount' =>  $total_amount,
        //                     'total_quantity' => $total_quantity,
        //                 ]);
        //             }
        //             if($total_hpp > 0){
        //                 Helper::addNewTransaction([
        //                     'date' => $created_sale->date,
        //                     'label' => "Sale Invoice for #". $created_sale->reference,
        //                     'description' => "Order ID: ".$created_sale->reference,
        //                     'purchase_id' => null,
        //                     'purchase_payment_id' => null,
        //                     'purchase_return_id' => null,
        //                     'purchase_return_payment_id' => null,
        //                     'sale_id' => $created_sale->id,
        //                     'sale_payment_id' => null,
        //                     'sale_return_id' => null,
        //                     'sale_return_payment_id' => null,
        //                 ], [
        //                     [
        //                         'subaccount_number' => '1-10100', // Piutang Usaha
        //                         'amount' => $total_amount,
        //                         'type' => 'debit'
        //                     ],
        //                     [
        //                         'subaccount_number' => '5-50000', // Beban Pokok Pendapatan
        //                         'amount' => $total_hpp,
        //                         'type' => 'debit'
        //                     ],
        //                     [
        //                         'subaccount_number' => '4-40000', // Pendapatan Usaha
        //                         'amount' => $total_amount,
        //                         'type' => 'credit'
        //                     ],[
        //                         'subaccount_number' => '1-10200', // Persediaan
        //                         'amount' => $total_hpp,
        //                         'type' => 'credit'
        //                     ]
        //                 ]);
        //             }else{
        //                 Helper::addNewTransaction([
        //                     'date' => $created_sale->date,
        //                     'label' => "Sale Invoice for #". $created_sale->reference,
        //                     'description' => "Order ID: ".$created_sale->reference,
        //                     'purchase_id' => null,
        //                     'purchase_payment_id' => null,
        //                     'purchase_return_id' => null,
        //                     'purchase_return_payment_id' => null,
        //                     'sale_id' => $created_sale->id,
        //                     'sale_payment_id' => null,
        //                     'sale_return_id' => null,
        //                     'sale_return_payment_id' => null,
        //                 ], [
        //                     [
        //                         'subaccount_number' => '1-10100', // Piutang Usaha
        //                         'amount' => $total_amount,
        //                         'type' => 'debit'
        //                     ],
        //                     [
        //                         'subaccount_number' => '4-40001', // Pendapatan Usaha
        //                         'amount' => $total_amount,
        //                         'type' => 'credit'
        //                     ]
        //                 ]);
        //             }
        //             $created_payment = SalePayment::create([
        //                 'date' => Carbon::createFromFormat('d/m/Y', $ks[1])->format('Y-m-d H:i:s'),
        //                 'reference' => 'INV/'.$created_sale->reference,
        //                 'amount' => $total_amount,
        //                 'sale_id' => $created_sale->id,
        //                 'payment_method' => '1-10002'
        //             ]);
        //             Helper::addNewTransaction([
        //                 'date'=> $ks[1],
        //                 'label' => "Payment for Sales Order #".$created_sale->reference,
        //                 'description' => "Sale ID: ".$created_sale->reference,
        //                 'purchase_id' => null,
        //                 'purchase_payment_id' => null,
        //                 'purchase_return_id' => null,
        //                 'purchase_return_payment_id' => null,
        //                 'sale_id' => null,
        //                 'sale_payment_id' => $created_payment->id,
        //                 'sale_return_id' => null,
        //                 'sale_return_payment_id' => null,
        //             ], [
        //                 [
        //                     'subaccount_number' => '1-10100', // Piutang Usaha
        //                     'amount' => $created_payment->amount,
        //                     'type' => 'debit'
        //                 ],
        //                 [
        //                     'subaccount_number' => $created_payment->payment_method, // Kas
        //                     'amount' => $created_payment->amount,
        //                     'type' => 'credit'
        //                 ]
        //             ]);
        //             $total_amount = 0;
        //             $total_quantity = 0;
        //             $total_hpp = 0;
        //             $header_temp = trim($ks[0]);
        //             $created_sale = Sale::where('note','=',$ks[0])->first();
        //             if(!$created_sale){
        //                 $customer = Customer::where('customer_name', $ks[10])->first();
        //                 if(!$customer){
        //                     $customer = Customer::create([
        //                         'customer_name' => $ks[10],
        //                         'customer_email'=> $ks[12],
        //                         'customer_phone'=> $ks[11],
        //                         'city' => $ks[14],
        //                         'address'=> $ks[14],
        //                         'country' => 'Indonesia'
        //                     ]);
        //                     // dd($customer);
        //                 }
        //                 $created_sale = Sale::create([
        //                     'date' => Carbon::createFromFormat('d/m/Y', $ks[1])->format('Y-m-d H:i:s'),
        //                     'reference' =>$ks[0],
        //                     'customer_id' => $customer->id,
        //                     'customer_name' => $customer->customer_name,
        //                     'tax_percentage' => 0,
        //                     'discount_percentage' => 0,
        //                     'shipping_amount' => 0,
        //                     'paid_amount' => $ks[9],
        //                     'total_amount' => $ks[9],
        //                     'total_quantity' => $ks[4],
        //                     'fee_amount' => $ks[16],
        //                     'due_amount' => 0,
        //                     'status' => 'Completed',
        //                     'payment_status' => 'Paid',
        //                     'payment_method' => '1-10002',
        //                     'note' => $ks[0],
        //                     'tax_amount' => 0,
        //                     'discount_amount' => 0,
        //                 ]);
        //                 $product = Product::where('product_code', $ks[2])->first();
        //                 if(!$product){
        //                     // dd($ks[2]);
        //                     $product = Product::create([
        //                         'category_id' => 99,
        //                         'accessory_code' => '-',
        //                         'product_name' => $ks[3],
        //                         'product_code' => $ks[2],
        //                         'product_barcode_symbology' => 'C128',
        //                         'product_price' => 0,
        //                         'product_cost' => 0,
        //                         'product_unit' => 'Unit',
        //                         'product_quantity' => 0,
        //                         'product_stock_alert' => -1,
        //                     ]);
        //                 }
        //                 SaleDetails::create([
        //                     'sale_id' => $created_sale->id,
        //                     'product_id' => $product->id,
        //                     'product_name' => $product->product_name,
        //                     'product_code' => $product->product_code,
        //                     'product_cost'=> $product->product_cost,
        //                     'warehouse_id'=> 99,
        //                     'quantity' => $ks[4],
        //                     'price' => (int)$ks[8] / $ks[4],
        //                     'unit_price' => (int)$ks[8] / $ks[4],
        //                     'sub_total' => $ks[8],
        //                     'product_discount_amount' => 0,
        //                     'product_discount_type' => 'fixed',
        //                     'product_tax_amount' => 0,
        //                 ]);
        //                 $mutation = Mutation::with('product')->where('product_id', $product->id)
        //                 ->where('warehouse_id', 99)
        //                 ->latest()->first();
        //                 $_stock_early = $mutation ? $mutation->stock_last : 0;
        //                 $_stock_in = 0;
        //                 $_stock_out = $ks[4];
        //                 $_stock_last = $_stock_early - $_stock_out;
        //                 // dd($purchase->id)
        //                 // $purchase = Purchase::where('id', $ks[1])->first();
        //                 Mutation::create([
        //                     'reference' => $created_sale->reference,
        //                     'date' => $created_sale->date,
        //                     'mutation_type' => "Out",
        //                     'note' => "Mutation for Sale: ". $created_sale->reference,
        //                     'warehouse_id' => 99,
        //                     'product_id' => $product->id,
        //                     'stock_early' => $_stock_early,
        //                     'stock_in' => $_stock_in,
        //                     'stock_out'=> $_stock_out,
        //                     'stock_last'=> $_stock_last,
        //                 ]);

        //                 $product->update([
        //                     'product_quantity' => $_stock_last,
        //                     'product_price' => (int)$ks[8] / $ks[4]
        //                 ]);
        //                 $total_amount += (int)$ks[8];
        //                 $total_quantity += $ks[4];
        //                 $total_hpp += $product->product_cost * $ks[4];
        //             }
        //             if($index == array_key_last($konsyinasi)){
        //                 // dd($ks, $total_amount, $total_hpp, $total_quantity);
        //                 if($total_amount > 0){
        //                     $created_sale = Sale::where('note','=',$header_temp)->first();
        //                     // dd($created_sale);
        //                     if($total_amount > $created_sale->total_amount){
        //                         $created_sale->update([
        //                             'paid_amount' => $total_amount,
        //                             'total_amount' =>  $total_amount,
        //                             'total_quantity' => $total_quantity,
        //                         ]);
        //                     }
        //                     if($total_hpp > 0){
        //                         Helper::addNewTransaction([
        //                             'date' => $created_sale->date,
        //                             'label' => "Sale Invoice for #". $created_sale->reference,
        //                             'description' => "Order ID: ".$created_sale->reference,
        //                             'purchase_id' => null,
        //                             'purchase_payment_id' => null,
        //                             'purchase_return_id' => null,
        //                             'purchase_return_payment_id' => null,
        //                             'sale_id' => $created_sale->id,
        //                             'sale_payment_id' => null,
        //                             'sale_return_id' => null,
        //                             'sale_return_payment_id' => null,
        //                         ], [
        //                             [
        //                                 'subaccount_number' => '1-10100', // Piutang Usaha
        //                                 'amount' => $total_amount,
        //                                 'type' => 'debit'
        //                             ],
        //                             [
        //                                 'subaccount_number' => '5-50000', // Beban Pokok Pendapatan
        //                                 'amount' => $total_hpp,
        //                                 'type' => 'debit'
        //                             ],
        //                             [
        //                                 'subaccount_number' => '4-40000', // Pendapatan Usaha
        //                                 'amount' => $total_amount,
        //                                 'type' => 'credit'
        //                             ],[
        //                                 'subaccount_number' => '1-10200', // Persediaan
        //                                 'amount' => $total_hpp,
        //                                 'type' => 'credit'
        //                             ]
        //                         ]);
        //                     }else{
        //                         Helper::addNewTransaction([
        //                             'date' => $created_sale->date,
        //                             'label' => "Sale Invoice for #". $created_sale->reference,
        //                             'description' => "Order ID: ".$created_sale->reference,
        //                             'purchase_id' => null,
        //                             'purchase_payment_id' => null,
        //                             'purchase_return_id' => null,
        //                             'purchase_return_payment_id' => null,
        //                             'sale_id' => $created_sale->id,
        //                             'sale_payment_id' => null,
        //                             'sale_return_id' => null,
        //                             'sale_return_payment_id' => null,
        //                         ], [
        //                             [
        //                                 'subaccount_number' => '1-10100', // Piutang Usaha
        //                                 'amount' => $total_amount,
        //                                 'type' => 'debit'
        //                             ],
        //                             [
        //                                 'subaccount_number' => '4-40001', // Pendapatan Usaha
        //                                 'amount' => $total_amount,
        //                                 'type' => 'credit'
        //                             ]
        //                         ]);
        //                     }
        //                     $created_payment = SalePayment::create([
        //                         'date' => Carbon::createFromFormat('d/m/Y', $ks[1])->format('Y-m-d H:i:s'),
        //                         'reference' => 'INV/'.$created_sale->reference,
        //                         'amount' => $total_amount,
        //                         'sale_id' => $created_sale->id,
        //                         'payment_method' => '1-10002'
        //                     ]);
        //                     Helper::addNewTransaction([
        //                         'date'=> $ks[1],
        //                         'label' => "Payment for Sales Order #".$created_sale->reference,
        //                         'description' => "Sale ID: ".$created_sale->reference,
        //                         'purchase_id' => null,
        //                         'purchase_payment_id' => null,
        //                         'purchase_return_id' => null,
        //                         'purchase_return_payment_id' => null,
        //                         'sale_id' => null,
        //                         'sale_payment_id' => $created_payment->id,
        //                         'sale_return_id' => null,
        //                         'sale_return_payment_id' => null,
        //                     ], [
        //                         [
        //                             'subaccount_number' => '1-10100', // Piutang Usaha
        //                             'amount' => $created_payment->amount,
        //                             'type' => 'debit'
        //                         ],
        //                         [
        //                             'subaccount_number' => $created_payment->payment_method, // Kas
        //                             'amount' => $created_payment->amount,
        //                             'type' => 'credit'
        //                         ]
        //                     ]);
        //                     $total_amount = 0;
        //                     $total_quantity = 0;
        //                     $total_hpp = 0;
        //                 }
        //             }
                    
        //         }else{
        //             $created_sale = Sale::where('note','=',$ks[0])->first();
        //             $customer = Customer::where('customer_name', $ks[10])->first();
        //             if(!$customer){
        //                 $customer = Customer::create([
        //                     'customer_name' => $ks[10],
        //                     'customer_email'=> $ks[12],
        //                     'customer_phone'=> $ks[11],
        //                     'city' => $ks[14],
        //                     'address'=> $ks[14],
        //                     'country' => 'Indonesia'
        //                 ]);
        //             }
        //             // dd($customer);
        //             if(!$created_sale){
        //                 $created_sale = Sale::create([
        //                     'date' => Carbon::createFromFormat('d/m/Y', $ks[1])->format('Y-m-d H:i:s'),
        //                     'reference' =>$ks[0],
        //                     'customer_id' => $customer->id,
        //                     'customer_name' => $customer->customer_name,
        //                     'tax_percentage' => 0,
        //                     'discount_percentage' => 0,
        //                     'shipping_amount' => 0,
        //                     'paid_amount' => $ks[9],
        //                     'total_amount' => $ks[9],
        //                     'total_quantity' => $ks[4],
        //                     'fee_amount' => $ks[16],
        //                     'due_amount' => 0,
        //                     'status' => 'Completed',
        //                     'payment_status' => 'Paid',
        //                     'payment_method' => '1-10002',
        //                     'note' => $ks[0],
        //                     'tax_amount' => 0,
        //                     'discount_amount' => 0,
        //                 ]);
        //             }
        //             $product = Product::where('product_code', $ks[2])->first();
        //             if(!$product){
        //                 // dd($ks[2]);
        //                 $product = Product::create([
        //                     'category_id' => 99,
        //                     'accessory_code' => '-',
        //                     'product_name' => $ks[3],
        //                     'product_code' => $ks[2],
        //                     'product_barcode_symbology' => 'C128',
        //                     'product_price' => 0,
        //                     'product_cost' => 0,
        //                     'product_unit' => 'Unit',
        //                     'product_quantity' => 0,
        //                     'product_stock_alert' => -1,
        //                 ]);
        //             }
        //             SaleDetails::create([
        //                 'sale_id' => $created_sale->id,
        //                 'product_id' => $product->id,
        //                 'product_name' => $product->product_name,
        //                 'product_code' => $product->product_code,
        //                 'product_cost'=> $product->product_cost,
        //                 'warehouse_id'=> 99,
        //                 'quantity' => $ks[4],
        //                 'price' =>(int) $ks[8] / $ks[4],
        //                 'unit_price' =>(int) $ks[8] / $ks[4],
        //                 'sub_total' => $ks[8],
        //                 'product_discount_amount' => 0,
        //                 'product_discount_type' => 'fixed',
        //                 'product_tax_amount' => 0,
        //             ]);
        //             $mutation = Mutation::with('product')->where('product_id', $product->id)
        //             ->where('warehouse_id', 99)
        //             ->latest()->first();
        //             $_stock_early = $mutation ? $mutation->stock_last : 0;
        //             $_stock_in = 0;
        //             $_stock_out = $ks[4];
        //             $_stock_last = $_stock_early - $_stock_out;
        //             // dd($purchase->id)
        //             // $purchase = Purchase::where('id', $ks[1])->first();
        //             Mutation::create([
        //                 'reference' => $created_sale->reference,
        //                 'date' => $created_sale->date,
        //                 'mutation_type' => "Out",
        //                 'note' => "Mutation for Sale: ". $created_sale->reference,
        //                 'warehouse_id' => 99,
        //                 'product_id' => $product->id,
        //                 'stock_early' => $_stock_early,
        //                 'stock_in' => $_stock_in,
        //                 'stock_out'=> $_stock_out,
        //                 'stock_last'=> $_stock_last,
        //             ]);
        //             $product->update([
        //                 'product_quantity' => $_stock_last,
        //                 'product_price' => (int)$ks[8] / $ks[4]
        //             ]);
        //             $total_amount += (int)$ks[8];
        //             $total_quantity += $ks[4];
        //             $total_hpp += $product->product_cost * $ks[4];
        //             $header_temp = trim($ks[0]);
        //             // dd($header_temp);
                    
        //         }
        //     }else{
        //         $product = Product::where('product_code', $ks[2])->first();
        //         // dd($created_sale->id);
        //         // dd($product);
        //         if(!$product){
        //             $product = Product::create([
        //                 'category_id' => 99,
        //                 'accessory_code' => '-',
        //                 'product_name' => $ks[3],
        //                 'product_code' => $ks[2],
        //                 'product_barcode_symbology' => 'C128',
        //                 'product_price' => 0,
        //                 'product_cost' => 0,
        //                 'product_unit' => 'Unit',
        //                 'product_quantity' => 0,
        //                 'product_stock_alert' => -1,
        //             ]);
        //         }
        //         SaleDetails::create([
        //             'sale_id' => $created_sale->id,
        //             'product_id' => $product->id,
        //             'product_name' => $product->product_name,
        //             'product_code' => $product->product_code,
        //             'product_cost'=> $product->product_cost,
        //             'warehouse_id'=> 99,
        //             'quantity' => $ks[4],
        //             'price' => (int)$ks[8] / $ks[4],
        //             'unit_price' => (int)$ks[8] / $ks[4],
        //             'sub_total' => $ks[8],
        //             'product_discount_amount' => 0,
        //             'product_discount_type' => 'fixed',
        //             'product_tax_amount' => 0,
        //         ]);
        //         $mutation = Mutation::with('product')->where('product_id', $product->id)
        //         ->where('warehouse_id', 99)
        //         ->latest()->first();
        //         $_stock_early = $mutation ? $mutation->stock_last : 0;
        //         $_stock_in = 0;
        //         $_stock_out = $ks[4];
        //         $_stock_last = $_stock_early - $_stock_out;
        //         // dd($purchase->id)
        //         // $purchase = Purchase::where('id', $ks[1])->first();
        //         Mutation::create([
        //             'reference' => $created_sale->reference,
        //             'date' => $created_sale->date,
        //             'mutation_type' => "Out",
        //             'note' => "Mutation for Sale: ". $created_sale->reference,
        //             'warehouse_id' => 99,
        //             'product_id' => $product->id,
        //             'stock_early' => $_stock_early,
        //             'stock_in' => $_stock_in,
        //             'stock_out'=> $_stock_out,
        //             'stock_last'=> $_stock_last,
        //         ]);
        //         $product->update([
        //             'product_quantity' => $_stock_last,
        //             'product_price' => (int)$ks[8] / $ks[4]
        //         ]);
        //         $total_amount +=(int) $ks[8];
        //         $total_quantity += $ks[4];
        //         $total_hpp += $product->product_cost * $ks[4];

        //         if($index == array_key_last($konsyinasi)){
        //             // dd($ks, $total_amount, $total_hpp, $total_quantity);
        //             if($total_amount > 0){
        //                 $created_sale = Sale::where('note','=',$header_temp)->first();
        //                 // dd($created_sale);
        //                 if($total_amount > $created_sale->total_amount){
        //                     $created_sale->update([
        //                         'paid_amount' => $total_amount,
        //                         'total_amount' =>  $total_amount,
        //                         'total_quantity' => $total_quantity,
        //                     ]);
        //                 }
        //                 if($total_hpp > 0){
        //                     Helper::addNewTransaction([
        //                         'date' => $created_sale->date,
        //                         'label' => "Sale Invoice for #". $created_sale->reference,
        //                         'description' => "Order ID: ".$created_sale->reference,
        //                         'purchase_id' => null,
        //                         'purchase_payment_id' => null,
        //                         'purchase_return_id' => null,
        //                         'purchase_return_payment_id' => null,
        //                         'sale_id' => $created_sale->id,
        //                         'sale_payment_id' => null,
        //                         'sale_return_id' => null,
        //                         'sale_return_payment_id' => null,
        //                     ], [
        //                         [
        //                             'subaccount_number' => '1-10100', // Piutang Usaha
        //                             'amount' => $total_amount,
        //                             'type' => 'debit'
        //                         ],
        //                         [
        //                             'subaccount_number' => '5-50000', // Beban Pokok Pendapatan
        //                             'amount' => $total_hpp,
        //                             'type' => 'debit'
        //                         ],
        //                         [
        //                             'subaccount_number' => '4-40000', // Pendapatan Usaha
        //                             'amount' => $total_amount,
        //                             'type' => 'credit'
        //                         ],[
        //                             'subaccount_number' => '1-10200', // Persediaan
        //                             'amount' => $total_hpp,
        //                             'type' => 'credit'
        //                         ]
        //                     ]);
        //                 }else{
        //                     Helper::addNewTransaction([
        //                         'date' => $created_sale->date,
        //                         'label' => "Sale Invoice for #". $created_sale->reference,
        //                         'description' => "Order ID: ".$created_sale->reference,
        //                         'purchase_id' => null,
        //                         'purchase_payment_id' => null,
        //                         'purchase_return_id' => null,
        //                         'purchase_return_payment_id' => null,
        //                         'sale_id' => $created_sale->id,
        //                         'sale_payment_id' => null,
        //                         'sale_return_id' => null,
        //                         'sale_return_payment_id' => null,
        //                     ], [
        //                         [
        //                             'subaccount_number' => '1-10100', // Piutang Usaha
        //                             'amount' => $total_amount,
        //                             'type' => 'debit'
        //                         ],
        //                         [
        //                             'subaccount_number' => '4-40001', // Pendapatan Usaha
        //                             'amount' => $total_amount,
        //                             'type' => 'credit'
        //                         ]
        //                     ]);
        //                 }
        //                 $created_payment = SalePayment::create([
        //                     'date' => Carbon::createFromFormat('d/m/Y', $ks[1])->format('Y-m-d H:i:s'),
        //                     'reference' => 'INV/'.$created_sale->reference,
        //                     'amount' => $total_amount,
        //                     'sale_id' => $created_sale->id,
        //                     'payment_method' => '1-10002'
        //                 ]);
        //                 Helper::addNewTransaction([
        //                     'date'=> $ks[1],
        //                     'label' => "Payment for Sales Order #".$created_sale->reference,
        //                     'description' => "Sale ID: ".$created_sale->reference,
        //                     'purchase_id' => null,
        //                     'purchase_payment_id' => null,
        //                     'purchase_return_id' => null,
        //                     'purchase_return_payment_id' => null,
        //                     'sale_id' => null,
        //                     'sale_payment_id' => $created_payment->id,
        //                     'sale_return_id' => null,
        //                     'sale_return_payment_id' => null,
        //                 ], [
        //                     [
        //                         'subaccount_number' => '1-10100', // Piutang Usaha
        //                         'amount' => $created_payment->amount,
        //                         'type' => 'debit'
        //                     ],
        //                     [
        //                         'subaccount_number' => $created_payment->payment_method, // Kas
        //                         'amount' => $created_payment->amount,
        //                         'type' => 'credit'
        //                     ]
        //                 ]);
        //                 $total_amount = 0;
        //                 $total_quantity = 0;
        //                 $total_hpp = 0;
        //             }
        //         }
        //     }
        // }



        //  //pembelian header
        //  $csvFileName = "header_nov.csv";
        //  $csvFile = public_path('csv/' . $csvFileName);
        //  $konsyinasi = $this->readCSV($csvFile,array('delimiter' => ','));
        // //  dd($konsyinasi);    
        //  $header_temp;
        //  foreach($konsyinasi as $ks){
        //     // dd(Carbon::createFromFormat('d/m/Y',$ks[2])->subHour(7)->format('Y-m-d H:i:s'));
        //      $purchase = Purchase::create([
        //          'id' => $ks[1],
        //          'date' =>Carbon::createFromFormat('d/m/Y',$ks[2])->subHour(7)->format('Y-m-d H:i:s'),
        //          'due_date' => $ks[10],
        //          'reference_supplier'=> $ks[0],
        //          'supplier_id' => $ks[3],
        //          'supplier_name' => Supplier::findOrFail($ks[3])->supplier_name,
        //          'tax_percentage' => 0,
        //          'discount_percentage' => 0,
        //          'shipping_amount' => $ks[5],
        //          'paid_amount' => $ks[8] == 'Paid' ? $ks[6] : 0 ,
        //          'total_amount' => (int)$ks[6],
        //          'due_amount' => $ks[8] == 'Unpaid' ? $ks[6] : 0,
        //          'status' => $ks[7],
        //          'total_quantity' => $ks[11],
        //          'payment_status' => $ks[8],
        //          'payment_method' => $ks[9],
        //          'note' => $ks[0],
        //          'tax_amount' => 0,
        //          'discount_amount' => 0,
        //      ]);
        //      Helper::addNewTransaction([
        //          'date' => $ks[2],
        //          'label' => "Purchase Invoice for #". $purchase->reference,
        //          'description' => "Order ID: ".$purchase->reference,
        //          'purchase_id' => $purchase->id,
        //          'purchase_payment_id' => null,
        //          'purchase_return_id' => null,
        //          'purchase_return_payment_id' => null,
        //          'sale_id' => null,
        //          'sale_payment_id' => null,
        //          'sale_return_id' => null,
        //          'sale_return_payment_id' => null,
        //      ], [
        //          [
        //              'subaccount_number' => '1-10200', // Beban Pokok Pendapatan
        //              'amount' => $purchase->total_amount,
        //              'type' => 'debit'
        //          ],
        //          [
        //              'subaccount_number' => '2-20100', // Hutang usaha
        //              'amount' => $purchase->total_amount,
        //              'type' => 'credit'
        //          ]
        //      ]);
        //      if($ks[8] == 'Paid'){
        //          $created_payment = PurchasePayment::create([
        //              'date' => $ks[2],
        //              'reference' => $purchase->reference,
        //              'amount' => $purchase->paid_amount,
        //              'note' => 'Payment',
        //              'purchase_id' => $purchase->id,
        //              'payment_method' => '1-10002'
        //          ]);
        //          Helper::addNewTransaction([
        //              'date'=> $ks[2],
        //              'label' => "Payment for Purchase Order #".$purchase->reference,
        //              'description' => "Purchase ID: ".$purchase->reference,
        //              'purchase_id' => null,
        //              'purchase_payment_id' => $created_payment->id,
        //              'purchase_return_id' => null,
        //              'purchase_return_payment_id' => null,
        //              'sale_id' => null,
        //              'sale_payment_id' => null,
        //              'sale_return_id' => null,
        //              'sale_return_payment_id' => null,
        //          ], [
        //              [
        //                  'subaccount_number' => '1-10200', // Persediaan dalam pengiriman
        //                  'amount' => $created_payment->amount,
        //                  'type' => 'debit'
        //              ],
        //              [
        //                  'subaccount_number' => $created_payment->payment_method, // Kas
        //                  'amount' => $created_payment->amount,
        //                  'type' => 'credit'
        //              ]
        //          ]);
 
        //         }
        //     }




        //  //purchase_detail
        //  $csvFileName = "nov.csv";
        //  $csvFile = public_path('csv/' . $csvFileName);
        //  $konsyinasi = $this->readCSV($csvFile,array('delimiter' => ','));
        //  $header_temp;
        //  $jasa_kirim=0;
        // //  dd($konsyinasi);
        //  foreach($konsyinasi as $ks){
        //      // $header_temp = $ks[0];
        //      $product = Product::where('product_code', $ks[3])->first();
        //      if(!$product){
        //          $product = Product::create([
        //              'category_id' => 99,
        //              'accessory_code' => '-',
        //              'product_name' => $ks[4],
        //              'product_code' => $ks[3],
        //              'product_barcode_symbology' => 'C128',
        //              'product_price' => 0,
        //              'product_cost' => 0,
        //              'product_unit' => 'Unit',
        //              'product_quantity' => 0,
        //              'product_stock_alert' => -1,
        //          ]);
        //      }
             
        //      // $product = Product::findOrFail($cart_item->id);
        //      PurchaseDetail::create([
        //          'purchase_id' => $ks[1],
        //          'product_id' => $product->id,
        //          'product_name' => $product->product_name,
        //          'product_code' => $product->product_code,
        //          'quantity' => $ks[5],
        //          'price' => $ks[6],
        //          'unit_price' => $ks[6],
        //          'sub_total' => (int)$ks[6] * (int)$ks[5],
        //          'product_discount_amount' => 0,
        //          'product_discount_type' => 'fixed',
        //          'product_tax_amount' => 0,
        //      ]);
        //      if($ks[3] != 'OTH-SERTRANSPORT' && $ks[3] != 'OTH-PETIKAYU'){
        //         $mutation = Mutation::with('product')->where('product_id', $product->id)
        //         ->where('warehouse_id', 99)
        //         ->latest()->first();
        //         if($mutation){
        //             if($mutation->stock_last == 0){
        //                 $product->update([
        //                     'product_cost' => (int)$ks[6] + ($ks[9] / $ks[14]),
        //                     'product_quantity' => $product->product_quantity + $ks[5]
        //                 ]);
                        
        //             }else{
        //                 $product->update([
        //                     'product_cost' => ($product->product_cost * $product->product_quantity + ((int)$ks[6] * (int)$ks[5])) / ((int)$ks[5] + $product->product_quantity),
        //                     'product_quantity' => $product->product_quantity + $ks[5]
        //                 ]);
        //             }
        //         }else{
        //             $product->update([
        //                 'product_cost' => (int)$ks[6] + ((int)$ks[9] / (int)$ks[14]),
        //                 'product_quantity' => $product->product_quantity + $ks[5]
        //                 // 'product_quantity' => $product->product_quantity + $cart_item->qty
        //             ]);
        //         }
                
        //         $_stock_early = $mutation ? $mutation->stock_last : 0;
        //         $_stock_in = $ks[5];
        //         $_stock_out = 0;
        //         $_stock_last = $_stock_early + $_stock_in;
        //         // dd($purchase->id)
        //         $purchase = Purchase::where('id', $ks[1])->first();
        //         Mutation::create([
        //             'reference' => $purchase->reference,
        //             'date' => $purchase->date,
        //             'mutation_type' => "In",
        //             'note' => "Mutation for Purchase: ". $purchase->reference,
        //             'warehouse_id' => 99,
        //             'product_id' => $product->id,
        //             'stock_early' => $_stock_early,
        //             'stock_in' => $_stock_in,
        //             'stock_out'=> $_stock_out,
        //             'stock_last'=> $_stock_last,
        //         ]);
        //     }
        //  }
 


        // DB::transaction(function () use ($request) {
        //     if($request->mutation_type == "Out"){
        //         foreach ($request->product_ids as $key => $id) {
        //             $mutation = Mutation::where('product_id', $id)
        //             ->where('warehouse_id', $request->warehouse_out_id)
        //             ->latest()->first();
        //             $_stock_early = $mutation ? $mutation->stock_last : 0;
        //             $_stock_in = 0;
        //             $_stock_out = $request->quantities[$key];
        //             $_stock_last = $_stock_early - $_stock_out;
        //             $number = Mutation::max('id') + 1;
                    
        //             $mutation = Mutation::create([
        //                 'reference' => $request->reference,
        //                 'date' => $request->date,
        //                 'mutation_type' => $request->mutation_type,
        //                 'note' => $request->note,
        //                 'warehouse_id' => $request->warehouse_out_id,
        //                 'product_id' => $id,
        //                 'stock_early' => $_stock_early,
        //                 'stock_in' => $_stock_in,
        //                 'stock_out'=> $_stock_out,
        //                 'stock_last'=> $_stock_last,
        //             ]);
        //         }
        //     }else if($request->mutation_type == "In"){
        //         foreach ($request->product_ids as $key => $id) {
        //             $mutation = Mutation::where('product_id', $id)
        //             ->where('warehouse_id', $request->warehouse_in_id)->latest()->first();
        //             $_stock_early = $mutation ? $mutation->stock_last : 0;
        //             $_stock_in = $request->quantities[$key];
        //             $_stock_out = 0;
        //             $_stock_last = $_stock_early + $_stock_in;
        //             $number = Mutation::max('id') + 1;
                    
        //             $mutation = Mutation::create([
        //                 'reference' => $request->reference,
        //                 'date' => $request->date,
        //                 'mutation_type' => $request->mutation_type,
        //                 'note' => $request->note,
        //                 'warehouse_id' => $request->warehouse_in_id,
        //                 'product_id' => $id,
        //                 'stock_early' => $_stock_early,
        //                 'stock_in' => $_stock_in,
        //                 'stock_out'=> $_stock_out,
        //                 'stock_last'=> $_stock_last,
        //             ]);
        //         }
        //     }else if($request->mutation_type == "Transfer"){
        //         foreach ($request->product_ids as $key => $id) {
        //             $mutation = Mutation::where('product_id', $id)
        //             ->where('warehouse_id', $request->warehouse_out_id)
        //             ->latest()->first();
        //             $_stock_early = $mutation ? $mutation->stock_last : 0;
        //             $_stock_in = 0;
        //             $_stock_out = $request->quantities[$key];
        //             $_stock_last = $_stock_early - $_stock_out;
        //             $number = Mutation::max('id') + 1;
                    
        //             $mutation = Mutation::create([
        //                 'reference' => $request->reference,
        //                 'date' => $request->date,
        //                 'mutation_type' => $request->mutation_type,
        //                 'note' => $request->note,
        //                 'warehouse_id' => $request->warehouse_out_id,
        //                 'product_id' => $id,
        //                 'stock_early' => $_stock_early,
        //                 'stock_in' => $_stock_in,
        //                 'stock_out'=> $_stock_out,
        //                 'stock_last'=> $_stock_last,
        //             ]);
        //             $_stock_early = $mutation ? $mutation->stock_last : 0;
        //             $_stock_in = $request->quantities[$key];
        //             $_stock_out = 0;
        //             $_stock_last = $_stock_early + $_stock_in;
                    
        //             $mutation = Mutation::create([
        //                 'reference' => $request->reference,
        //                 'date' => $request->date,
        //                 'mutation_type' => $request->mutation_type,
        //                 'note' => $request->note,
        //                 'warehouse_id' => $request->warehouse_in_id,
        //                 'product_id' => $id,
        //                 'stock_early' => $_stock_early,
        //                 'stock_in' => $_stock_in,
        //                 'stock_out'=> $_stock_out,
        //                 'stock_last'=> $_stock_early + $request->stock_in - $request->stock_out,
        //             ]);
        //         }
        //     }
            
        // });
 
        return view('mutation::create');
    }

    public function readCSV($csvFile, $array)
    {
        $file_handle = fopen($csvFile, 'r');
        while (!feof($file_handle)) {
            $line_of_text[] = fgetcsv($file_handle, 0, $array['delimiter']);
        }
        fclose($file_handle);
        return $line_of_text;
    }
    
  
    public function store(Request $request) {

        abort_if(Gate::denies('create_mutations'), 403);
        
        //penjualan header & detil
        $csvFileName = "penj_b_agus.csv";
        $csvFile = public_path('csv/' . $csvFileName);
        $konsyinasi = $this->readCSV($csvFile,array('delimiter' => ','));
        $header_temp="";
        $total_amount = 0;
        $total_quantity = 0;
        $total_hpp = 0;
        
        foreach($konsyinasi as $ks){
            if($header_temp != $ks[1]){
                if($total_amount > 0){
                    $created_sale = Sale::where('reference','=',$header_temp)->first();
                    if($total_amount > $created_sale->total_amount){
                        $created_sale->update([
                            'paid_amount' => $total_amount,
                            'total_amount' =>  $total_amount,
                            'total_quantity' => $total_quantity,
                        ]);
                    }
                    if($total_hpp > 0){
                        Helper::addNewTransaction([
                            'date' => $created_sale->date,
                            'label' => "Sale Invoice for #". $created_sale->reference,
                            'description' => "Order ID: ".$created_sale->reference,
                            'purchase_id' => null,
                            'purchase_payment_id' => null,
                            'purchase_return_id' => null,
                            'purchase_return_payment_id' => null,
                            'sale_id' => $created_sale->id,
                            'sale_payment_id' => null,
                            'sale_return_id' => null,
                            'sale_return_payment_id' => null,
                        ], [
                            [
                                'subaccount_number' => '1-10100', // Piutang Usaha
                                'amount' => $total_amount,
                                'type' => 'debit'
                            ],
                            [
                                'subaccount_number' => '5-50000', // Beban Pokok Pendapatan
                                'amount' => $total_hpp,
                                'type' => 'debit'
                            ],
                            [
                                'subaccount_number' => '4-40000', // Pendapatan Usaha
                                'amount' => $total_amount,
                                'type' => 'credit'
                            ],[
                                'subaccount_number' => '1-10200', // Persediaan
                                'amount' => $total_hpp,
                                'type' => 'credit'
                            ]
                        ]);
                    }else{
                        Helper::addNewTransaction([
                            'date' => $created_sale->date,
                            'label' => "Sale Invoice for #". $created_sale->reference,
                            'description' => "Order ID: ".$created_sale->reference,
                            'purchase_id' => null,
                            'purchase_payment_id' => null,
                            'purchase_return_id' => null,
                            'purchase_return_payment_id' => null,
                            'sale_id' => $created_sale->id,
                            'sale_payment_id' => null,
                            'sale_return_id' => null,
                            'sale_return_payment_id' => null,
                        ], [
                            [
                                'subaccount_number' => '1-10100', // Piutang Usaha
                                'amount' => $total_amount,
                                'type' => 'debit'
                            ],
                            [
                                'subaccount_number' => '4-40001', // Pendapatan Usaha
                                'amount' => $total_amount,
                                'type' => 'credit'
                            ]
                        ]);
                    }
                    $created_payment = SalePayment::create([
                        'date' => $ks[0],
                        'reference' => 'INV/'.$created_sale->reference,
                        'amount' => $total_amount,
                        'sale_id' => $created_sale->id,
                        'payment_method' => '1-10002'
                    ]);
                    Helper::addNewTransaction([
                        'date'=> $ks[2],
                        'label' => "Payment for Sales Order #".$created_sale->reference,
                        'description' => "Sale ID: ".$created_sale->reference,
                        'purchase_id' => null,
                        'purchase_payment_id' => null,
                        'purchase_return_id' => null,
                        'purchase_return_payment_id' => null,
                        'sale_id' => null,
                        'sale_payment_id' => $created_payment->id,
                        'sale_return_id' => null,
                        'sale_return_payment_id' => null,
                    ], [
                        [
                            'subaccount_number' => '1-10100', // Piutang Usaha
                            'amount' => $created_payment->amount,
                            'type' => 'debit'
                        ],
                        [
                            'subaccount_number' => $created_payment->payment_method, // Kas
                            'amount' => $created_payment->amount,
                            'type' => 'credit'
                        ]
                    ]);
                    $total_amount = 0;
                    $total_quantity = 0;
                    $total_hpp = 0;
                    $header_temp = $ks[1];
                    $created_sale = Sale::where('reference','=',$ks[1])->first();
                    if(!$created_sale){
                        $customer = Customer::where('customer_name', $ks[10]);
                        if(!$customer){
                            $customer = Customer::create([
                                'customer_name' => $ks[10],
                                'customer_email'=> $ks[12],
                                'customer_phone'=> $ks[11],
                                'city' => $ks[14],
                                'address'=> $ks[14],
                                'country' => 'Indonesia'
                            ]);
                        }
                        $created_sale = Sale::create([
                            'date' => $ks[0],
                            'reference' =>$ks[1],
                            'customer_id' => $customer->id,
                            'customer_name' => $customer->customer_name,
                            'tax_percentage' => 0,
                            'discount_percentage' => 0,
                            'shipping_amount' => 0,
                            'paid_amount' => $ks[9],
                            'total_amount' => $ks[9],
                            'total_quantity' => $ks[4],
                            'fee_amount' => $ks[16],
                            'due_amount' => 0,
                            'status' => 'Completed',
                            'payment_status' => 'Paid',
                            'payment_method' => '1-10002',
                            'note' => $ks[1],
                            'tax_amount' => 0,
                            'discount_amount' => 0,
                        ]);
                        $product = Product::where('product_code', $ks[2])->first();
                        SaleDetails::create([
                            'sale_id' => $sale->id,
                            'product_id' => $product->id,
                            'product_name' => $product->product_name,
                            'product_code' => $product->product_code,
                            'product_cost'=> $product->product_cost,
                            'warehouse_id'=> 99,
                            'quantity' => $ks[4],
                            'price' => $ks[8] / $ks[4],
                            'unit_price' => $ks[8] / $ks[4],
                            'sub_total' => $ks[8],
                            'product_discount_amount' => 0,
                            'product_discount_type' => 'fixed',
                            'product_tax_amount' => 0,
                        ]);
                        $total_amount += $ks[8];
                        $total_quantity += $ks[4];
                        $total_hpp += $product->product_cost * $ks[4];
                    }
                    
                }else{
                    $created_sale = Sale::where('reference','=',$ks[1])->first();
                    $customer = Customer::where('customer_name', $ks[10]);
                    if(!$customer){
                        $customer = Customer::create([
                            'customer_name' => $ks[10],
                            'customer_email'=> $ks[12],
                            'customer_phone'=> $ks[11],
                            'city' => $ks[14],
                            'address'=> $ks[14],
                            'country' => 'Indonesia'
                        ]);
                    }
                    if(!$created_sale){
                        $created_sale = Sale::create([
                            'date' => $ks[0],
                            'reference' =>$ks[1],
                            'customer_id' => $customer->id,
                            'customer_name' => $customer->customer_name,
                            'tax_percentage' => 0,
                            'discount_percentage' => 0,
                            'shipping_amount' => 0,
                            'paid_amount' => $ks[9],
                            'total_amount' => $ks[9],
                            'total_quantity' => $ks[4],
                            'fee_amount' => $ks[16],
                            'due_amount' => 0,
                            'status' => 'Completed',
                            'payment_status' => 'Paid',
                            'payment_method' => '1-10002',
                            'note' => $ks[1],
                            'tax_amount' => 0,
                            'discount_amount' => 0,
                        ]);
                    }
                    $product = Product::where('product_code', $ks[2])->first();
                    SaleDetails::create([
                        'sale_id' => $sale->id,
                        'product_id' => $product->id,
                        'product_name' => $product->product_name,
                        'product_code' => $product->product_code,
                        'product_cost'=> $product->product_cost,
                        'warehouse_id'=> 99,
                        'quantity' => $ks[4],
                        'price' => $ks[8] / $ks[4],
                        'unit_price' => $ks[8] / $ks[4],
                        'sub_total' => $ks[8],
                        'product_discount_amount' => 0,
                        'product_discount_type' => 'fixed',
                        'product_tax_amount' => 0,
                    ]);
                    $total_amount += $ks[8];
                    $total_quantity += $ks[4];
                    $total_hpp += $product->product_cost * $ks[4];
                    $header_temp = $ks[1];
                }
            }else{
                $product = Product::where('product_code', $ks[2])->first();
                SaleDetails::create([
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'product_name' => $product->product_name,
                    'product_code' => $product->product_code,
                    'product_cost'=> $product->product_cost,
                    'warehouse_id'=> 99,
                    'quantity' => $ks[4],
                    'price' => $ks[8] / $ks[4],
                    'unit_price' => $ks[8] / $ks[4],
                    'sub_total' => $ks[8],
                    'product_discount_amount' => 0,
                    'product_discount_type' => 'fixed',
                    'product_tax_amount' => 0,
                ]);
                $total_amount += $ks[8];
                $total_quantity += $ks[4];
                $total_hpp += $product->product_cost * $ks[4];
            }
            
           
            
        }





       
        //purchase_detail
        $csvFileName = "juni.csv";
        $csvFile = public_path('csv/' . $csvFileName);
        $konsyinasi = $this->readCSV($csvFile,array('delimiter' => ';'));
        $header_temp;
        foreach($konsyinasi as $ks){
            // $header_temp = $ks[0];
            $product = Product::where('product_code', $ks[3])->first();
            if(!$product){
                $product = Product::create([
                    'category_id' => 99,
                    'accessory_code' => '-',
                    'product_name' => $ks[4],
                    'product_code' => $ks[3],
                    'product_barcode_symbology' => 'C128',
                    'product_price' => 0,
                    'product_cost' => 0,
                    'product_unit' => 'Unit',
                    'product_quantity' => 0,
                    'product_stock_alert' => -1,
                ]);
            }
            
            // $product = Product::findOrFail($cart_item->id);
            PurchaseDetail::create([
                'purchase_id' => $ks[1],
                'product_id' => $product->id,
                'product_name' => $product->product_name,
                'product_code' => $product->product_code,
                'quantity' => $ks[5],
                'price' => $ks[6],
                'unit_price' => $ks[6],
                'sub_total' => $ks[6] * $ks[5],
                'product_discount_amount' => 0,
                'product_discount_type' => 'fixed',
                'product_tax_amount' => 0,
            ]);
            $mutation = Mutation::with('product')->where('product_id', $product->id)
            ->where('warehouse_id', 99)
            ->latest()->first();
            if($mutation){
                if($mutation->stock_last == 0){
                    $product->update([
                        'product_cost' => $ks[6] + ($ks[9] / $ks[14]),
                        'product_quantity' => $product->product_quantity + $ks[5]
                    ]);
                    
                }else{
                    $product->update([
                        'product_cost' => ($product->product_cost * $product->product_quantity + ($ks[6] * $ks[5])) / ($ks[5] + $product->product_quantity),
                        'product_quantity' => $product->product_quantity + $ks[5]
                    ]);
                }
            }else{
                $product->update([
                    'product_cost' => $ks[6] + ($ks[9] / $ks[14]),
                    'product_quantity' => $product->product_quantity + $ks[5]
                    // 'product_quantity' => $product->product_quantity + $cart_item->qty
                ]);
            }
            
            $_stock_early = $mutation ? $mutation->stock_last : 0;
            $_stock_in = $ks[5];
            $_stock_out = 0;
            $_stock_last = $_stock_early + $_stock_in;
            // dd($purchase->id)
            $purchase = Purchase::where('id', $ks[1])->first();
            Mutation::create([
                'reference' => $purchase->reference,
                'date' => $purchase->date,
                'mutation_type' => "In",
                'note' => "Mutation for Purchase: ". $purchase->reference,
                'warehouse_id' => 99,
                'product_id' => $product->id,
                'stock_early' => $_stock_early,
                'stock_in' => $_stock_in,
                'stock_out'=> $_stock_out,
                'stock_last'=> $_stock_last,
            ]);
            
        }


        





        // dd(Mutation::where('product_id', 1716)
        //                                 ->latest()->get()->unique('warehouse_id')->sum('stock_in'));
     
        // //purchase Header
        // $csvFileName = "juni.csv";
        // $csvFile = public_path('csv/' . $csvFileName);
        // $konsyinasi = $this->readCSV($csvFile,array('delimiter' => ';'));
        // $header_temp;
        // foreach($konsyinasi as $ks){
        //     if($ks == $header_temp){
        //         $purchase = Purchase::create([
        //             'id' => $ks[1],
        //             'date' => $ks[2],
        //             'due_date' => $ks[10],
        //             'reference_supplier'=> $ks[0],
        //             'supplier_id' => $ks[4],
        //             'supplier_name' => Supplier::findOrFail($ks[4])->supplier_name,
        //             'tax_percentage' => 0,
        //             'discount_percentage' => 0,
        //             'shipping_amount' => $ks[5],
        //             'paid_amount' => $ks[8] == "Unpaid" ? 0 : $ks[6],
        //             'total_amount' => $ks[6],
        //             'due_amount' => $ks[8] == "Unpaid" ? $ks[6] : 0,
        //             'status' => $ks[7],
        //             'total_quantity' => $ks[11],
        //             'payment_status' => $ks[8],
        //             'payment_method' => '-',
        //             'note' => '-',
        //             'tax_amount' => 0,
        //             'discount_amount' =>0,
        //         ]);
                
        //         $subaccount = AccountingSubaccount::find($validatedData['subaccount_id']);
        //         Helper::addNewTransaction([
        //             'label' => "Payment for Supplier Order",
        //             'description' => "Order ID: ".$orderHeaderData->id,
        //             'training_center_id' => null,
        //             'ibo_id' => '1',
        //             'ibo_order_header_id' => $orderHeaderData->id,
        //             'tc_order_header_id' => null,
        //         ], [
        //             [
        //                 'subaccount_number' => '1-10201', // Persediaan dalam pengiriman
        //                 'amount' => $totalPrice,
        //                 'type' => 'debit'
        //             ],
        //             [
        //                 'subaccount_number' => $subaccount->subaccount_number, // Kas
        //                 'amount' => $totalPrice,
        //                 'type' => 'credit'
        //             ]
        //         ]);
        //         Helper::addNewTransaction([
        //             'label' => "Payment for Supplier Order",
        //             'description' => "Order ID: ".$orderHeaderData->id,
        //             'training_center_id' => null,
        //             'ibo_id' => '1',
        //             'ibo_order_header_id' => $orderHeaderData->id,
        //             'tc_order_header_id' => null,
        //         ], [
        //             [
        //                 'subaccount_number' => '1-10201', // Persediaan dalam pengiriman
        //                 'amount' => $totalPrice,
        //                 'type' => 'debit'
        //             ],
        //             [
        //                 'subaccount_number' => $subaccount->subaccount_number, // Kas
        //                 'amount' => $totalPrice,
        //                 'type' => 'credit'
        //             ]
        //         ]);
        //         foreach (Cart::instance('purchase')->content() as $cart_item) {
        //             PurchaseDetail::create([
        //                 'purchase_id' => $purchase->id,
        //                 'product_id' => $cart_item->id,
        //                 'product_name' => $cart_item->name,
        //                 'product_code' => $cart_item->options->code,
        //                 'quantity' => $cart_item->qty,
        //                 'price' => $cart_item->price * 1,
        //                 'unit_price' => $cart_item->options->unit_price * 1,
        //                 'sub_total' => $cart_item->options->sub_total * 1,
        //                 'product_discount_amount' => $cart_item->options->product_discount * 1,
        //                 'product_discount_type' => $cart_item->options->product_discount_type,
        //                 'product_tax_amount' => $cart_item->options->product_tax * 1,
        //             ]);
    
        //             if ($request->status == 'Completed') {
        //                 $mutation = Mutation::with('product')->where('product_id', $cart_item->id)
        //                 ->where('warehouse_id', $cart_item->options->warehouse_id)
        //                 ->latest()->first();
        //                 $product = Product::findOrFail($cart_item->id);
        //                 if($mutation){
        //                     if($mutation->stock_last == 0){
        //                         $product->update([
        //                             'product_cost' => (($cart_item->options->sub_total - ($cart_item->options->sub_total * $request->discount_percentage)) / 
        //                                 ($cart_item->qty) + ($request->shipping_amount / $request->total_quantity)),
        //                             // 'product_quantity' => $product->product_quantity + $cart_item->qty
        //                         ]);
        //                     }else{
        //                         $product->update([
        //                             'product_cost' => ((($mutation['product']->product_cost * $mutation->stock_last)  +
        //                                 (($cart_item->options->sub_total - ($cart_item->options->sub_total
        //                                  * $request->discount_percentage)) + (($request->shipping_amount
        //                                  / $request->total_quantity) * $cart_item->qty)))
        //                                   / ($mutation->stock_last + $cart_item->qty)),
        //                             // 'product_quantity' => $product->product_quantity + $cart_item->qty
        //                         ]);
        //                     }
        //                 }else{
        //                     $product->update([
        //                         'product_cost' => (($cart_item->options->sub_total - ($cart_item->options->sub_total * $request->discount_percentage)) / 
        //                             ($cart_item->qty) + ($request->shipping_amount / $request->total_quantity)),
        //                         // 'product_quantity' => $product->product_quantity + $cart_item->qty
        //                     ]);
        //                 }
                        
        //                 $_stock_early = $mutation ? $mutation->stock_last : 0;
        //                 $_stock_in = $cart_item->qty;
        //                 $_stock_out = 0;
        //                 $_stock_last = $_stock_early + $_stock_in;
        //                 // dd($purchase->id)
    
        //                 Mutation::create([
        //                     'reference' => $purchase->reference,
        //                     'date' => $request->date,
        //                     'mutation_type' => "In",
        //                     'note' => "Mutation for Purchase: ". $purchase->reference,
        //                     'warehouse_id' => $cart_item->options->warehouse_id,
        //                     'product_id' => $cart_item->id,
        //                     'stock_early' => $_stock_early,
        //                     'stock_in' => $_stock_in,
        //                     'stock_out'=> $_stock_out,
        //                     'stock_last'=> $_stock_last,
        //                 ]);
    
        //                 $subaccount = AccountingSubaccount::find($validatedData['subaccount_id']);
        //                 Helper::addNewTransaction([
        //                     'label' => "Payment for Supplier Order",
        //                     'description' => "Order ID: ".$orderHeaderData->id,
        //                     'training_center_id' => null,
        //                     'ibo_id' => '1',
        //                     'ibo_order_header_id' => $orderHeaderData->id,
        //                     'tc_order_header_id' => null,
        //                 ], [
        //                     [
        //                         'subaccount_number' => '1-10201', // Persediaan dalam pengiriman
        //                         'amount' => $totalPrice,
        //                         'type' => 'debit'
        //                     ],
        //                     [
        //                         'subaccount_number' => $subaccount->subaccount_number, // Kas
        //                         'amount' => $totalPrice,
        //                         'type' => 'credit'
        //                     ]
        //                 ]);
    
        //             }
        //         }
        //     }



        //     if(isset($ks[4])){
        //         if($ks[4] == "CR"){
        //             $total[0] += $ks[3];    
        //             $credit[$index] = new Credit();
        //             $credit[$index]->tanggal = $ks[0];
        //             $credit[$index]->nominal = $ks[3];
        //             $credit[$index]->keterangan = $ks[1];
        //         }else if($ks[4] == "DB"){
        //             $total[1] += $ks[3]; 
        //             $debit[$index] = new Debit();
        //             $debit[$index]->tanggal = $ks[0];
        //             $debit[$index]->nominal = $ks[3];
        //             $debit[$index]->keterangan = $ks[1];
        //         }
        //         $index++;
        //     }
        // }
        // dd($total);

        // foreach($konsyinasi as $ks){
            // $product = Product::where('product_code', $ks[1])->first();
            // if(!$product){
            //     $product = Product::create([
            //         'category_id' => 99,
            //         'accessory_code' => '-',
            //         'product_name' => 'dummy',
            //         'product_code' => $ks[1],
            //         'product_barcode_symbology' => 'C128',
            //         'product_price' => 0,
            //         'product_cost' => 0,
            //         'product_unit' => 'Unit',
            //         'product_quantity' => 0,
            //         'product_stock_alert' => -1,
            //     ]);
            // }
        //     $mutation = Mutation::where('product_id', $product->id)
        //             ->where('warehouse_id', 2)
        //             ->latest()->first();
        //     $_stock_early = $mutation ? $mutation->stock_last : 0;
        //     $_stock_in = $ks[2];
        //     $_stock_out = 0;
        //     $_stock_last = $_stock_early + $_stock_in;
        //     $mutation = Mutation::create([
        //         'reference' => $ks[0],
        //         'date' => $request->date,
        //         'mutation_type' => 'In',
        //         'note' => $request->note,
        //         'warehouse_id' => 2,
        //         'product_id' => $product->id,
        //         'stock_early' => $_stock_early,
        //         'stock_in' => $_stock_in,
        //         'stock_out'=> $_stock_out,
        //         'stock_last'=> $_stock_last,
        //     ]);

            
        //     $product->update([
        //         'product_quantity' => Mutation::where('product_id', $product->id)
        //                                 ->latest()->get()->unique('warehouse_id')
        //                                 ->sum('stock_last'),
        //     ]);
        // }
        // $request->validate([
        //     'reference'   => 'required|string|max:255',
        //     'date'        => 'required|date',
        //     'note'        => 'nullable|string|max:1000',
        //     'product_ids' => 'required',
        //     'quantities'  => 'required',
        //     'type'       => 'required'
        // ]);
        

        // DB::transaction(function () use ($request) {
        //     if($request->mutation_type == "Out"){
        //         foreach ($request->product_ids as $key => $id) {
        //             $mutation = Mutation::where('product_id', $id)
        //             ->where('warehouse_id', $request->warehouse_out_id)
        //             ->latest()->first();
        //             $_stock_early = $mutation ? $mutation->stock_last : 0;
        //             $_stock_in = 0;
        //             $_stock_out = $request->quantities[$key];
        //             $_stock_last = $_stock_early - $_stock_out;
        //             $number = Mutation::max('id') + 1;
                    
        //             $mutation = Mutation::create([
        //                 'reference' => $request->reference,
        //                 'date' => $request->date,
        //                 'mutation_type' => $request->mutation_type,
        //                 'note' => $request->note,
        //                 'warehouse_id' => $request->warehouse_out_id,
        //                 'product_id' => $id,
        //                 'stock_early' => $_stock_early,
        //                 'stock_in' => $_stock_in,
        //                 'stock_out'=> $_stock_out,
        //                 'stock_last'=> $_stock_last,
        //             ]);
        //         }
        //     }else if($request->mutation_type == "In"){
        //         foreach ($request->product_ids as $key => $id) {
        //             $mutation = Mutation::where('product_id', $id)
        //             ->where('warehouse_id', $request->warehouse_in_id)->latest()->first();
        //             $_stock_early = $mutation ? $mutation->stock_last : 0;
        //             $_stock_in = $request->quantities[$key];
        //             $_stock_out = 0;
        //             $_stock_last = $_stock_early + $_stock_in;
        //             $number = Mutation::max('id') + 1;
                    
        //             $mutation = Mutation::create([
        //                 'reference' => $request->reference,
        //                 'date' => $request->date,
        //                 'mutation_type' => $request->mutation_type,
        //                 'note' => $request->note,
        //                 'warehouse_id' => $request->warehouse_in_id,
        //                 'product_id' => $id,
        //                 'stock_early' => $_stock_early,
        //                 'stock_in' => $_stock_in,
        //                 'stock_out'=> $_stock_out,
        //                 'stock_last'=> $_stock_last,
        //             ]);
        //         }
        //     }else if($request->mutation_type == "Transfer"){
        //         foreach ($request->product_ids as $key => $id) {
        //             $mutation = Mutation::where('product_id', $id)
        //             ->where('warehouse_id', $request->warehouse_out_id)
        //             ->latest()->first();
        //             $_stock_early = $mutation ? $mutation->stock_last : 0;
        //             $_stock_in = 0;
        //             $_stock_out = $request->quantities[$key];
        //             $_stock_last = $_stock_early - $_stock_out;
        //             $number = Mutation::max('id') + 1;
                    
        //             $mutation = Mutation::create([
        //                 'reference' => $request->reference,
        //                 'date' => $request->date,
        //                 'mutation_type' => $request->mutation_type,
        //                 'note' => $request->note,
        //                 'warehouse_id' => $request->warehouse_out_id,
        //                 'product_id' => $id,
        //                 'stock_early' => $_stock_early,
        //                 'stock_in' => $_stock_in,
        //                 'stock_out'=> $_stock_out,
        //                 'stock_last'=> $_stock_last,
        //             ]);

        //             $_stock_early = $mutation ? $mutation->stock_last : 0;
        //             $_stock_in = $request->quantities[$key];
        //             $_stock_out = 0;
        //             $_stock_last = $_stock_early + $_stock_in;
                    
        //             $mutation = Mutation::create([
        //                 'reference' => $request->reference,
        //                 'date' => $request->date,
        //                 'mutation_type' => $request->mutation_type,
        //                 'note' => $request->note,
        //                 'warehouse_id' => $request->warehouse_in_id,
        //                 'product_id' => $id,
        //                 'stock_early' => $_stock_early,
        //                 'stock_in' => $_stock_in,
        //                 'stock_out'=> $_stock_out,
        //                 'stock_last'=> $_stock_early + $request->stock_in - $request->stock_out,
        //             ]);
        //         }
        //     }
            
        // });


        toast('Mutation Created!', 'success');

        return redirect()->route('mutations.index');
    }


    public function show(Mutation $mutation) {
        abort_if(Gate::denies('show_mutations'), 403);

        return view('mutation::show', compact('mutation'));
    }


    public function edit(Mutation $mutation) {
        abort_if(Gate::denies('edit_mutations'), 403);

        return view('mutation::edit', compact('mutation'));
    }


    public function update(Request $request, Mutation $mutation) {
        abort_if(Gate::denies('edit_mutations'), 403);

        $request->validate([
            'reference'   => 'required|string|max:255',
            'date'        => 'required|date',
            'note'        => 'nullable|string|max:1000',
            'product_ids' => 'required',
            'quantities'  => 'required',
            'types'       => 'required'
        ]);

        DB::transaction(function () use ($request, $mutation) {
            $mutation->update([
                'reference' => $request->reference,
                'date'      => $request->date,
                'note'      => $request->note
            ]);

            foreach ($mutation->adjustedProducts as $adjustedProduct) {
                $product = Product::findOrFail($adjustedProduct->product->id);

                if ($adjustedProduct->type == 'add') {
                    $product->update([
                        'product_quantity' => $product->product_quantity - $adjustedProduct->quantity
                    ]);
                } elseif ($adjustedProduct->type == 'sub') {
                    $product->update([
                        'product_quantity' => $product->product_quantity + $adjustedProduct->quantity
                    ]);
                }

                $adjustedProduct->delete();
            }

            foreach ($request->product_ids as $key => $id) {
                Mutation::create([
                    'mutation_id' => $mutation->id,
                    'product_id'    => $id,
                    'quantity'      => $request->quantities[$key],
                    'type'          => $request->types[$key]
                ]);

                $product = Product::findOrFail($id);

                if ($request->types[$key] == 'add') {
                    $product->update([
                        'product_quantity' => $product->product_quantity + $request->quantities[$key]
                    ]);
                } elseif ($request->types[$key] == 'sub') {
                    $product->update([
                        'product_quantity' => $product->product_quantity - $request->quantities[$key]
                    ]);
                }
            }
        });

        toast('Mutation Updated!', 'info');

        return redirect()->route('mutations.index');
    }


    public function destroy(Mutation $mutation) {
        abort_if(Gate::denies('delete_mutations'), 403);

        $mutation->delete();

        toast('Mutation Deleted!', 'warning');

        return redirect()->route('mutations.index');
    }
}
