<?php

namespace App\Http\Controllers;

use App\Http\Resources\AccountingAccountCollection;
use App\Http\Resources\AccountingAccountCollectionResource;
use App\Models\AccountingAccount;
use App\Models\AccountingSubaccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AccountingAccountsController extends Controller
{
    function accounts(Request $request)
    {
        $user = Auth::user();
        // return $user;
        $limit = $request->query('limit', 100);
        $search = trim($request->query('search', ''));

        $accounts = AccountingAccount::query()
            ->when(!empty($search), function ($query) use ($search) {
                return $query->where('account_number', 'LIKE', '%' . $search . '%')
                    ->orWhere('account_name', 'LIKE', '%' . $search . '%');
            })
            ->orderByRaw('CAST(account_number AS UNSIGNED) ASC');

        $total = AccountingAccount::query()
            ->count();
        return [
            'page' => new AccountingAccountCollection($accounts->paginate($limit)),
            'meta' => [
                'total' => $total
            ]
        ];
    }

    function subaccounts()
    {
        $user = Auth::user();
        return AccountingSubaccount::query()
            ->join('accounting_accounts', 'accounting_accounts.id', '=', 'accounting_subaccounts.accounting_account_id')
            ->where('accounting_accounts.is_active', '=', '1')
            ->select('accounting_subaccounts.*', 'accounting_accounts.account_number')->get();
    }

    function accountDetail($id)
    {
        $user = Auth::user();
        $account = AccountingAccount::query()->where('account_number', '=', $id)->first();
        return new AccountingAccountCollectionResource($account);
    }

    function initAccounts()
    {
        $user = Auth::user();
        $accounts = AccountingAccount::query();

        if ($accounts->count() > 0) {
            return response()->json([
                'message' => 'Accounts already initialized'
            ], 500);
        }

        $initAccounts = [
            [
                'account_number' => '3',
                'account_name' => 'Kas & Bank',
                'description' => 'Kas & Bank',
                'subaccounts' => [
                    [
                        'subaccount_number' => '1-10001',
                        'subaccount_name' => 'Kas',
                        'description' => 'Kas Tunai',
                    ],
                    [
                        'subaccount_number' => '1-10002',
                        'subaccount_name' => 'Rekening Bank',
                        'description' => 'Rekening Bank',
                    ],
                ]
            ],
            [
                'account_number' => '1',
                'account_name' => 'Akun Piutang',
                'description' => 'Piutang',
                'subaccounts' => [
                    [
                        'subaccount_number' => '1-10100',
                        'subaccount_name' => 'Piutang Usaha',
                        'description' => 'Piutang Usaha',
                    ],
                    [
                        'subaccount_number' => '1-10101',
                        'subaccount_name' => 'Piutang Belum Ditagih',
                        'description' => 'Piutang Belum Ditagih',
                    ],
                    [
                        'subaccount_number' => '1-10102',
                        'subaccount_name' => 'Cadangan Kerugian Piutang',
                        'description' => 'Cadangan Kerugian Piutang',
                    ],
                ]
            ],
            [
                'account_number' => '4',
                'account_name' => 'Persediaan',
                'description' => 'Persediaan',
                'subaccounts' => [
                    [
                        'subaccount_number' => '1-10200',
                        'subaccount_name' => 'Persediaan Barang',
                        'description' => 'Persediaan Barang',
                    ],
                    [
                        'subaccount_number' => '1-10201',
                        'subaccount_name' => 'Persediaan dalam Pengiriman',
                        'description' => 'Persediaan dalam Pengiriman',
                    ],
                ]
            ],
            [
                'account_number' => '2',
                'account_name' => 'Aktiva Lancar Lainnya',
                'description' => 'Aktiva Lancar Lainnya',
                'subaccounts' => [
                    [
                        'subaccount_number' => '1-10300',
                        'subaccount_name' => 'Piutang Lainnya',
                        'description' => 'Piutang Lainnya',
                    ],
                    [
                        'subaccount_number' => '1-10301',
                        'subaccount_name' => 'Piutang Karyawan',
                        'description' => 'Piutang Karyawan',
                    ],
                    [
                        'subaccount_number' => '1-10400',
                        'subaccount_name' => 'Dana Belum Disetor',
                        'description' => 'Dana Belum Disetor',
                    ],
                    [
                        'subaccount_number' => '1-10401',
                        'subaccount_name' => 'Aset Lancar Lainnya',
                        'description' => 'Aset Lancar Lainnya',
                    ],
                    [
                        'subaccount_number' => '1-10402',
                        'subaccount_name' => 'Biaya Dibayar Di Muka',
                        'description' => 'Biaya Dibayar Di Muka',
                    ],
                    [
                        'subaccount_number' => '1-10403',
                        'subaccount_name' => 'Uang Muka',
                        'description' => 'Uang Muka',
                    ],
                    [
                        'subaccount_number' => '1-10500',
                        'subaccount_name' => 'PPN Masukan',
                        'description' => 'PPN Masukan',
                    ],
                    [
                        'subaccount_number' => '1-10501',
                        'subaccount_name' => 'Pajak Dibayar Di Muka - PPh 22',
                        'description' => 'Pajak Dibayar Di Muka - PPh 22',
                    ],
                    [
                        'subaccount_number' => '1-10502',
                        'subaccount_name' => 'Pajak Dibayar Di Muka - PPh 23',
                        'description' => 'Pajak Dibayar Di Muka - PPh 23',
                    ],
                    [
                        'subaccount_number' => '1-10503',
                        'subaccount_name' => 'Pajak Dibayar Di Muka - PPh 25',
                        'description' => 'Pajak Dibayar Di Muka - PPh 25',
                    ],
                ]
            ],
            [
                'account_number' => '5',
                'account_name' => 'Aktiva Tetap',
                'description' => 'Aktiva Tetap',
                'subaccounts' => [
                    [
                        'subaccount_number' => '1-10700',
                        'subaccount_name' => 'Aset Tetap - Tanah',
                        'description' => 'Aset Tetap - Tanah',
                    ],
                    [
                        'subaccount_number' => '1-10701',
                        'subaccount_name' => 'Aset Tetap - Bangunan',
                        'description' => 'Aset Tetap - Bangunan',
                    ],
                    [
                        'subaccount_number' => '1-10702',
                        'subaccount_name' => 'Aset Tetap - Building Improvements',
                        'description' => 'Aset Tetap - Building Improvements',
                    ],
                    [
                        'subaccount_number' => '1-10703',
                        'subaccount_name' => 'Aset Tetap - Kendaraan',
                        'description' => 'Aset Tetap - Kendaraan',
                    ],
                    [
                        'subaccount_number' => '1-10704',
                        'subaccount_name' => 'Aset Tetap - Mesin & Peralatan',
                        'description' => 'Aset Tetap - Mesin & Peralatan',
                    ],
                    [
                        'subaccount_number' => '1-10705',
                        'subaccount_name' => 'Aset Tetap - Perlengkapan Kantor',
                        'description' => 'Aset Tetap - Perlengkapan Kantor',
                    ],
                    [
                        'subaccount_number' => '1-10706',
                        'subaccount_name' => 'Aset Tetap - Aset Sewa Guna Usaha',
                        'description' => 'Aset Tetap - Aset Sewa Guna Usaha',
                    ],
                    [
                        'subaccount_number' => '1-10707',
                        'subaccount_name' => 'Aset Tak Berwujud',
                        'description' => 'Aset Tak Berwujud',
                    ],
                ]
            ],
            [
                'account_number' => '7',
                'account_name' => 'Depresiasi & Amortisasi',
                'description' => 'Depresiasi & Amortisasi',
                'subaccounts' => [
                    [
                        'subaccount_number' => '1-10751',
                        'subaccount_name' => 'Akumulasi Penyusutan - Bangunan',
                        'description' => 'Akumulasi Penyusutan - Bangunan',
                    ],
                    [
                        'subaccount_number' => '1-10752',
                        'subaccount_name' => 'Akumulasi Penyusutan - Building Improvements',
                        'description' => 'Akumulasi Penyusutan - Building Improvements',
                    ],
                    [
                        'subaccount_number' => '1-10753',
                        'subaccount_name' => 'Akumulasi penyusutan - Kendaraan',
                        'description' => 'Akumulasi penyusutan - Kendaraan',
                    ],
                    [
                        'subaccount_number' => '1-10754',
                        'subaccount_name' => 'Akumulasi Penyusutan - Mesin & Peralatan',
                        'description' => 'Akumulasi Penyusutan - Mesin & Peralatan',
                    ],
                    [
                        'subaccount_number' => '1-10755',
                        'subaccount_name' => 'Akumulasi Penyusutan - Peralatan Kantor',
                        'description' => 'Akumulasi Penyusutan - Peralatan Kantor',
                    ],
                    [
                        'subaccount_number' => '1-10756',
                        'subaccount_name' => 'Akumulasi Penyusutan - Aset Sewa Guna Usaha',
                        'description' => 'Akumulasi Penyusutan - Aset Sewa Guna Usaha',
                    ],
                    [
                        'subaccount_number' => '1-10757',
                        'subaccount_name' => 'Akumulasi Amortisasi',
                        'description' => 'Akumulasi Amortisasi',
                    ],
                ]
            ],
            [
                'account_number' => '6',
                'account_name' => 'Aktiva Lainnya',
                'description' => 'Aktiva Lainnya',
                'subaccounts' => [
                    [
                        'subaccount_number' => '1-10800',
                        'subaccount_name' => 'Investasi',
                        'description' => 'Investasi',
                    ],
                ]
            ],
            [
                'account_number' => '8',
                'account_name' => 'Akun Hutang',
                'description' => 'Akun Hutang',
                'subaccounts' => [
                    [
                        'subaccount_number' => '2-20100',
                        'subaccount_name' => 'Hutang Usaha',
                        'description' => 'Hutang Usaha',
                    ],
                    [
                        'subaccount_number' => '2-20101',
                        'subaccount_name' => 'Hutang Belum Ditagih',
                        'description' => 'Hutang Belum Ditagih',
                    ],
                ]
            ],
            [
                'account_number' => '10',
                'account_name' => 'Kewajiban Lancar Lainnya',
                'description' => 'Kewajiban Lancar Lainnya',
                'subaccounts' => [
                    [
                        'subaccount_number' => '2-20200',
                        'subaccount_name' => 'Hutang Lain Lain',
                        'description' => 'Hutang Lain Lain',
                    ],
                    [
                        'subaccount_number' => '2-20201',
                        'subaccount_name' => 'Hutang Gaji',
                        'description' => 'Hutang Gaji',
                    ],
                    [
                        'subaccount_number' => '2-20202',
                        'subaccount_name' => 'Hutang Deviden',
                        'description' => 'Hutang Deviden',
                    ],
                    [
                        'subaccount_number' => '2-20203',
                        'subaccount_name' => 'Pendapatan Diterima Di Muka',
                        'description' => 'Pendapatan Diterima Di Muka',
                    ],
                    [
                        'subaccount_number' => '2-20205',
                        'subaccount_name' => 'Hutang Konsinyasi',
                        'description' => 'Hutang Konsinyasi',
                    ],
                    [
                        'subaccount_number' => '2-20301',
                        'subaccount_name' => 'Sarana Kantor Terhutang',
                        'description' => 'Sarana Kantor Terhutang',
                    ],
                    [
                        'subaccount_number' => '2-20302',
                        'subaccount_name' => 'Bunga Terhutang',
                        'description' => 'Bunga Terhutang',
                    ],
                    [
                        'subaccount_number' => '2-20399',
                        'subaccount_name' => 'Biaya Terhutang Lainnya',
                        'description' => 'Biaya Terhutang Lainnya',
                    ],
                    [
                        'subaccount_number' => '2-20400',
                        'subaccount_name' => 'Hutang Bank',
                        'description' => 'Hutang Bank',
                    ],
                    [
                        'subaccount_number' => '2-20500',
                        'subaccount_name' => 'PPN Keluaran',
                        'description' => 'PPN Keluaran',
                    ],
                    [
                        'subaccount_number' => '2-20501',
                        'subaccount_name' => 'Hutang Pajak - PPh 21',
                        'description' => 'Hutang Pajak - PPh 21',
                    ],
                    [
                        'subaccount_number' => '2-20502',
                        'subaccount_name' => 'Hutang Pajak - PPh 22',
                        'description' => 'Hutang Pajak - PPh 22',
                    ],
                    [
                        'subaccount_number' => '2-20503',
                        'subaccount_name' => 'Hutang Pajak - PPh 23',
                        'description' => 'Hutang Pajak - PPh 23',
                    ],
                    [
                        'subaccount_number' => '2-20504',
                        'subaccount_name' => 'Hutang Pajak - PPh 29',
                        'description' => 'Hutang Pajak - PPh 29',
                    ],
                    [
                        'subaccount_number' => '2-20599',
                        'subaccount_name' => 'Hutang Pajak Lainnya',
                        'description' => 'Hutang Pajak Lainnya',
                    ],
                    [
                        'subaccount_number' => '2-20600',
                        'subaccount_name' => 'Hutang dari Pemegang Saham',
                        'description' => 'Hutang dari Pemegang Saham',
                    ],
                    [
                        'subaccount_number' => '2-20601',
                        'subaccount_name' => 'Kewajiban Lancar Lainnya',
                        'description' => 'Kewajiban Lancar Lainnya',
                    ],
                ]
            ],
            [
                'account_number' => '11',
                'account_name' => 'Kewajiban Jangka Panjang',
                'description' => 'Kewajiban Jangka Panjang',
                'subaccounts' => [
                    [
                        'subaccount_number' => '2-20700',
                        'subaccount_name' => 'Kewajiban Manfaat Karyawan',
                        'description' => 'Kewajiban Manfaat Karyawan',
                    ],
                ]
            ],
            [
                'account_number' => '12',
                'account_name' => 'Ekuitas',
                'description' => 'Ekuitas',
                'subaccounts' => [
                    [
                        'subaccount_number' => '3-30000',
                        'subaccount_name' => 'Modal Saham',
                        'description' => 'Modal Saham',
                    ],
                    [
                        'subaccount_number' => '3-30001',
                        'subaccount_name' => 'Tambahan Modal Disetor',
                        'description' => 'Tambahan Modal Disetor',
                    ],
                    [
                        'subaccount_number' => '3-30100',
                        'subaccount_name' => 'Laba Ditahan',
                        'description' => 'Laba Ditahan',
                    ],
                    [
                        'subaccount_number' => '3-30200',
                        'subaccount_name' => 'Deviden',
                        'description' => 'Deviden',
                    ],
                    [
                        'subaccount_number' => '3-30300',
                        'subaccount_name' => 'Pendapatan Komprehensif Lainnya',
                        'description' => 'Pendapatan Komprehensif Lainnya',
                    ],
                    [
                        'subaccount_number' => '3-30999',
                        'subaccount_name' => 'Ekuitas Saldo Awal',
                        'description' => 'Ekuitas Saldo Awal',
                    ],
                ]
            ],
            [
                'account_number' => '13',
                'account_name' => 'Pendapatan',
                'description' => 'Pendapatan',
                'subaccounts' => [
                    [
                        'subaccount_number' => '4-40000',
                        'subaccount_name' => 'Pendapatan',
                        'description' => 'Pendapatan',
                    ],
                    [
                        'subaccount_number' => '4-40100',
                        'subaccount_name' => 'Diskon Penjualan',
                        'description' => 'Diskon Penjualan',
                    ],
                    [
                        'subaccount_number' => '4-40200',
                        'subaccount_name' => 'Retur Penjualan',
                        'description' => 'Retur Penjualan',
                    ],
                    [
                        'subaccount_number' => '4-40201',
                        'subaccount_name' => 'Pendapatan Belum Ditagih',
                        'description' => 'Pendapatan Belum Ditagih',
                    ],
                ]
            ],
            [
                'account_number' => '15',
                'account_name' => 'Harga Pokok Penjualan',
                'description' => 'Harga Pokok Penjualan',
                'subaccounts' => [
                    [
                        'subaccount_number' => '5-50000',
                        'subaccount_name' => 'Beban Pokok Pendapatan',
                        'description' => 'Beban Pokok Pendapatan',
                    ],
                    [
                        'subaccount_number' => '5-50100',
                        'subaccount_name' => 'Diskon Pembelian',
                        'description' => 'Diskon Pembelian',
                    ],
                    [
                        'subaccount_number' => '5-50200',
                        'subaccount_name' => 'Retur Pembelian',
                        'description' => 'Retur Pembelian',
                    ],
                    [
                        'subaccount_number' => '5-50300',
                        'subaccount_name' => 'Pengiriman & Pengangkutan',
                        'description' => 'Pengiriman & Pengangkutan',
                    ],
                    [
                        'subaccount_number' => '5-50400',
                        'subaccount_name' => 'Biaya Impor',
                        'description' => 'Biaya Impor',
                    ],
                    [
                        'subaccount_number' => '5-50500',
                        'subaccount_name' => 'Biaya Produksi',
                        'description' => 'Biaya Produksi',
                    ],
                    [
                        'subaccount_number' => '5-50600',
                        'subaccount_name' => 'Biaya Gaji',
                        'description' => 'Biaya Gaji',
                    ],
                ]
            ],
            [
                'account_number' => '16',
                'account_name' => 'Beban',
                'description' => 'Beban',
                'subaccounts' => [
                    [
                        'subaccount_number' => '6-60000',
                        'subaccount_name' => 'Biaya Penjualan',
                        'description' => 'Biaya Penjualan',
                    ],
                    [
                        'subaccount_number' => '6-60001',
                        'subaccount_name' => 'Iklan & Promosi',
                        'description' => 'Iklan & Promosi',
                    ],
                    [
                        'subaccount_number' => '6-60002',
                        'subaccount_name' => 'Komisi & Fee',
                        'description' => 'Komisi & Fee',
                    ],
                    [
                        'subaccount_number' => '6-60003',
                        'subaccount_name' => 'Bensin, Tol dan Parkir - Penjualan',
                        'description' => 'Bensin, Tol dan Parkir - Penjualan',
                    ],
                    [
                        'subaccount_number' => '6-60004',
                        'subaccount_name' => 'Perjalanan Dinas - Penjualan',
                        'description' => 'Perjalanan Dinas - Penjualan',
                    ],
                    [
                        'subaccount_number' => '6-60005',
                        'subaccount_name' => 'Komunikasi - Penjualan',
                        'description' => 'Komunikasi - Penjualan',
                    ],
                    [
                        'subaccount_number' => '6-60006',
                        'subaccount_name' => 'Marketing Lainnya',
                        'description' => 'Marketing Lainnya',
                    ],
                    [
                        'subaccount_number' => '6-60100',
                        'subaccount_name' => 'Biaya Umum & Administratif',
                        'description' => 'Biaya Umum & Administratif',
                    ],
                    [
                        'subaccount_number' => '6-60101',
                        'subaccount_name' => 'Gaji',
                        'description' => 'Gaji',
                    ],
                    [
                        'subaccount_number' => '6-60102',
                        'subaccount_name' => 'Upah',
                        'description' => 'Upah',
                    ],
                    [
                        'subaccount_number' => '6-60103',
                        'subaccount_name' => 'Makanan & Transportasi',
                        'description' => 'Makanan & Transportasi',
                    ],
                    [
                        'subaccount_number' => '6-60104',
                        'subaccount_name' => 'Lembur',
                        'description' => 'Lembur',
                    ],
                    [
                        'subaccount_number' => '6-60105',
                        'subaccount_name' => 'Pengobatan',
                        'description' => 'Pengobatan',
                    ],
                    [
                        'subaccount_number' => '6-60106',
                        'subaccount_name' => 'THR & Bonus',
                        'description' => 'THR & Bonus',
                    ],
                    [
                        'subaccount_number' => '6-60107',
                        'subaccount_name' => 'Jamsostek',
                        'description' => 'Jamsostek',
                    ],
                    [
                        'subaccount_number' => '6-60108',
                        'subaccount_name' => 'Insentif',
                        'description' => 'Insentif',
                    ],
                    [
                        'subaccount_number' => '6-60109',
                        'subaccount_name' => 'Pesangon',
                        'description' => 'Pesangon',
                    ],
                    [
                        'subaccount_number' => '6-60110',
                        'subaccount_name' => 'Manfaat dan Tunjangan Lain',
                        'description' => 'Manfaat dan Tunjangan Lain',
                    ],
                    [
                        'subaccount_number' => '6-60200',
                        'subaccount_name' => 'Donasi',
                        'description' => 'Donasi',
                    ],
                    [
                        'subaccount_number' => '6-60201',
                        'subaccount_name' => 'Hiburan',
                        'description' => 'Hiburan',
                    ],
                    [
                        'subaccount_number' => '6-60202',
                        'subaccount_name' => 'Bensin, Tol dan Parkir - Umum',
                        'description' => 'Bensin, Tol dan Parkir - Umum',
                    ],
                    [
                        'subaccount_number' => '6-60203',
                        'subaccount_name' => 'Perbaikan & Pemeliharaan',
                        'description' => 'Perbaikan & Pemeliharaan',
                    ],
                    [
                        'subaccount_number' => '6-60204',
                        'subaccount_name' => 'Perjalanan Dinas - Umum',
                        'description' => 'Perjalanan Dinas - Umum',
                    ],
                    [
                        'subaccount_number' => '6-60205',
                        'subaccount_name' => 'Makanan',
                        'description' => 'Makanan',
                    ],
                    [
                        'subaccount_number' => '6-60206',
                        'subaccount_name' => 'Komunikasi - Umum',
                        'description' => 'Komunikasi - Umum',
                    ],
                    [
                        'subaccount_number' => '6-60207',
                        'subaccount_name' => 'Iuran & Langganan',
                        'description' => 'Iuran & Langganan',
                    ],
                    [
                        'subaccount_number' => '6-60208',
                        'subaccount_name' => 'Asuransi',
                        'description' => 'Asuransi',
                    ],
                    [
                        'subaccount_number' => '6-60209',
                        'subaccount_name' => 'Legal & Profesional',
                        'description' => 'Legal & Profesional',
                    ],
                    [
                        'subaccount_number' => '6-60210',
                        'subaccount_name' => 'Beban Manfaat Karyawan',
                        'description' => 'Beban Manfaat Karyawan',
                    ],
                    [
                        'subaccount_number' => '6-60211',
                        'subaccount_name' => 'Sarana Kantor',
                        'description' => 'Sarana Kantor',
                    ],
                    [
                        'subaccount_number' => '6-60212',
                        'subaccount_name' => 'Pelatihan & Pengembangan',
                        'description' => 'Pelatihan & Pengembangan',
                    ],
                    [
                        'subaccount_number' => '6-60213',
                        'subaccount_name' => 'Beban Piutang Tak Tertagih',
                        'description' => 'Beban Piutang Tak Tertagih',
                    ],
                    [
                        'subaccount_number' => '6-60214',
                        'subaccount_name' => 'Pajak dan Perizinan',
                        'description' => 'Pajak dan Perizinan',
                    ],
                    [
                        'subaccount_number' => '6-60215',
                        'subaccount_name' => 'Denda',
                        'description' => 'Denda',
                    ],
                    [
                        'subaccount_number' => '6-60217',
                        'subaccount_name' => 'Listrik',
                        'description' => 'Listrik',
                    ],
                    [
                        'subaccount_number' => '6-60218',
                        'subaccount_name' => 'Air',
                        'description' => 'Air',
                    ],
                    [
                        'subaccount_number' => '6-60219',
                        'subaccount_name' => 'IPL',
                        'description' => 'IPL',
                    ],
                    [
                        'subaccount_number' => '6-60220',
                        'subaccount_name' => 'Langganan Software',
                        'description' => 'Langganan Software',
                    ],
                    [
                        'subaccount_number' => '6-60300',
                        'subaccount_name' => 'Beban Kantor',
                        'description' => 'Beban Kantor',
                    ],
                    [
                        'subaccount_number' => '6-60301',
                        'subaccount_name' => 'Alat Tulis Kantor & Printing',
                        'description' => 'Alat Tulis Kantor & Printing',
                    ],
                    [
                        'subaccount_number' => '6-60302',
                        'subaccount_name' => 'Bea Materai',
                        'description' => 'Bea Materai',
                    ],
                    [
                        'subaccount_number' => '6-60303',
                        'subaccount_name' => 'Keamanan dan Kebersihan',
                        'description' => 'Keamanan dan Kebersihan',
                    ],
                    [
                        'subaccount_number' => '6-60304',
                        'subaccount_name' => 'Supplies dan Material',
                        'description' => 'Supplies dan Material',
                    ],
                    [
                        'subaccount_number' => '6-60305',
                        'subaccount_name' => 'Pemborong',
                        'description' => 'Pemborong',
                    ],
                    [
                        'subaccount_number' => '6-60400',
                        'subaccount_name' => 'Biaya Sewa - Bangunan',
                        'description' => 'Biaya Sewa - Bangunan',
                    ],
                    [
                        'subaccount_number' => '6-60401',
                        'subaccount_name' => 'Biaya Sewa - Kendaraan',
                        'description' => 'Biaya Sewa - Kendaraan',
                    ],
                    [
                        'subaccount_number' => '6-60402',
                        'subaccount_name' => 'Biaya Sewa - Operasional',
                        'description' => 'Biaya Sewa - Operasional',
                    ],
                    [
                        'subaccount_number' => '6-60403',
                        'subaccount_name' => 'Biaya Sewa - Lain - lain',
                        'description' => 'Biaya Sewa - Lain - lain',
                    ],
                    [
                        'subaccount_number' => '6-60500',
                        'subaccount_name' => 'Penyusutan - Bangunan',
                        'description' => 'Penyusutan - Bangunan',
                    ],
                    [
                        'subaccount_number' => '6-60501',
                        'subaccount_name' => 'Penyusutan - Perbaikan Bangunan',
                        'description' => 'Penyusutan - Perbaikan Bangunan',
                    ],
                    [
                        'subaccount_number' => '6-60502',
                        'subaccount_name' => 'Penyusutan - Kendaraan',
                        'description' => 'Penyusutan - Kendaraan',
                    ],
                    [
                        'subaccount_number' => '6-60503',
                        'subaccount_name' => 'Penyusutan - Mesin & Peralatan',
                        'description' => 'Penyusutan - Mesin & Peralatan',
                    ],
                    [
                        'subaccount_number' => '6-60504',
                        'subaccount_name' => 'Penyusutan - Peralatan Kantor',
                        'description' => 'Penyusutan - Peralatan Kantor',
                    ],
                    [
                        'subaccount_number' => '6-60599',
                        'subaccount_name' => 'Penyusutan - Aset Sewa Guna Usaha',
                        'description' => 'Penyusutan - Aset Sewa Guna Usaha',
                    ],
                    [
                        'subaccount_number' => '6-60216',
                        'subaccount_name' => 'Pengeluaran Barang Rusak',
                        'description' => 'Pengeluaran Barang Rusak',
                    ],
                ]
            ],
            [
                'account_number' => '14',
                'account_name' => 'Pendapatan Lainnya',
                'description' => 'Pendapatan Lainnya',
                'subaccounts' => [
                    [
                        'subaccount_number' => '7-70000',
                        'subaccount_name' => 'Pendapatan Bunga - Bank',
                        'description' => 'Pendapatan Bunga - Bank',
                    ],
                    [
                        'subaccount_number' => '7-70001',
                        'subaccount_name' => 'Pendapatan Bunga - Deposito',
                        'description' => 'Pendapatan Bunga - Deposito',
                    ],
                    [
                        'subaccount_number' => '7-70002',
                        'subaccount_name' => 'Pendapatan Komisi - Barang Konsinyasi',
                        'description' => 'Pendapatan Komisi - Barang Konsinyasi',
                    ],
                    [
                        'subaccount_number' => '7-70003',
                        'subaccount_name' => 'Pembulatan',
                        'description' => 'Pembulatan',
                    ],
                    [
                        'subaccount_number' => '7-70099',
                        'subaccount_name' => 'Pendapatan Lain - lain',
                        'description' => 'Pendapatan Lain - lain',
                    ],
                ]
            ],
            [
                'account_number' => '17',
                'account_name' => 'Beban Lainnya',
                'description' => 'Beban Lainnya',
                'subaccounts' => [
                    [
                        'subaccount_number' => '8-80000',
                        'subaccount_name' => 'Beban Bunga',
                        'description' => 'Beban Bunga',
                    ],
                    [
                        'subaccount_number' => '8-80001',
                        'subaccount_name' => 'Provisi',
                        'description' => 'Provisi',
                    ],
                    [
                        'subaccount_number' => '8-80002',
                        'subaccount_name' => '(Laba)/Rugi Pelepasan Aset Tetap',
                        'description' => '(Laba)/Rugi Pelepasan Aset Tetap',
                    ],
                    [
                        'subaccount_number' => '8-80100',
                        'subaccount_name' => 'Penyesuaian Persediaan',
                        'description' => 'Penyesuaian Persediaan',
                    ],
                    [
                        'subaccount_number' => '8-80999',
                        'subaccount_name' => 'Beban Lain - lain',
                        'description' => 'Beban Lain - lain',
                    ],
                ]
            ]
        ];

        $data = array_map(function ($account) use ($user) {
            return [
                'account_number' => $account['account_number'],
                'account_name' => $account['account_name'],
                'description' => $account['description'],
                'subaccounts' => $account['subaccounts']
            ];
        }, $initAccounts);

        // return $data;

        DB::beginTransaction();
        foreach ($data as $account) {
            $this->createAccount($account);
        }
        DB::commit();

        return [
            'message' => 'OK'
        ];
    }

    function upsert(Request $request)
    {
        $data = $request->validate([
            'account_number' => 'required',
            'account_name' => 'required',
            'description' => 'required',
            'is_active' => 'required',
        ]);

        if ($request->filled('id')) {
            $account = AccountingAccount::find($request->input('id'));
            $account->update($data);
            return response()->json($account);
        }

        $account = $this->createAccount($data);
        return response()->json($account);
    }

    function upsertSubaccount(Request $request)
    {
        $data = $request->validate([
            'accounting_account_id' => 'required',
            'subaccount_number' => 'required',
            'subaccount_name' => 'required',
            'description' => 'required',
            'is_active' => 'required',
        ]);

        if ($request->filled('id')) {
            $account = AccountingSubaccount::find($request->input('id'));
            $account->update($data);
            return response()->json($account);
        }

        $account = AccountingSubaccount::create($data);
        return response()->json($account);
    }

    function createAccount($data)
    {
        $user = Auth::user();
        $finalData = [
            'account_number' => $data['account_number'],
            'account_name' => $data['account_name'],
            'description' => $data['description'],
        ];
        $account = AccountingAccount::create($finalData);
        if (isset($data['subaccounts'])) {
            foreach ($data['subaccounts'] as $subaccount) {
                $subaccount['accounting_account_id'] = $account->id;
                AccountingSubaccount::create($subaccount);
            }
        }
        return $account;
    }
}
