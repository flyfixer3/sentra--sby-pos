<?php

namespace App\Http\Controllers;

use App\Http\Resources\AccountingAccountCollection;
use App\Http\Resources\AccountingAccountCollectionResource;
use App\Models\AccountingAccount;
use App\Models\AccountingAccountMapping;
use App\Models\AccountingSubaccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountingAccountsController extends Controller
{
    public function accounts(Request $request)
    {
        $limit = max((int) $request->query('limit', 100), 1);
        $search = trim((string) $request->query('search', ''));

        $query = AccountingAccount::query()
            ->with('subaccounts')
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('account_number', 'like', '%' . $search . '%')
                        ->orWhere('account_name', 'like', '%' . $search . '%');
                });
            })
            ->orderByRaw('LENGTH(account_number) asc')
            ->orderBy('account_number');

        $paginator = $query->paginate($limit);

        return [
            'page' => new AccountingAccountCollection($paginator),
            'meta' => [
                'total' => $paginator->total(),
            ],
        ];
    }

    public function subaccounts()
    {
        return AccountingSubaccount::query()
            ->join('accounting_accounts', 'accounting_accounts.id', '=', 'accounting_subaccounts.accounting_account_id')
            ->where('accounting_accounts.is_active', true)
            ->where('accounting_subaccounts.is_active', true)
            ->select('accounting_subaccounts.*', 'accounting_accounts.account_number')
            ->orderBy('accounting_accounts.account_number')
            ->orderBy('accounting_subaccounts.subaccount_number')
            ->get();
    }

    public function accountDetail($id)
    {
        $account = AccountingAccount::query()
            ->with('subaccounts')
            ->where('account_number', $id)
            ->orWhere('id', $id)
            ->first();

        if ($account === null) {
            return response()->json([
                'message' => 'Account not found',
            ], 404);
        }

        return new AccountingAccountCollectionResource($account);
    }

    public function initAccounts()
    {
        if (AccountingAccount::query()->exists()) {
            return response()->json([
                'message' => 'Accounts already initialized',
            ], 422);
        }

        $accounts = $this->starterAccounts();

        DB::transaction(function () use ($accounts) {
            foreach ($accounts as $account) {
                $this->createAccount($account);
            }

            $this->seedDefaultMappings();
        });

        return [
            'message' => 'OK',
        ];
    }

    public function syncStarterAccounts()
    {
        $accounts = $this->starterAccounts();
        $createdAccounts = 0;
        $createdSubaccounts = 0;

        DB::transaction(function () use ($accounts, &$createdAccounts, &$createdSubaccounts) {
            foreach ($accounts as $accountData) {
                $account = AccountingAccount::query()->firstOrCreate(
                    ['account_number' => $accountData['account_number']],
                    [
                        'account_name' => $accountData['account_name'],
                        'description' => $accountData['description'],
                        'is_active' => $accountData['is_active'] ?? true,
                    ]
                );

                if ($account->wasRecentlyCreated) {
                    $createdAccounts++;
                }

                foreach ($accountData['subaccounts'] ?? [] as $subaccountData) {
                    $subaccount = AccountingSubaccount::query()->firstOrCreate(
                        ['subaccount_number' => $subaccountData['subaccount_number']],
                        [
                            'accounting_account_id' => $account->id,
                            'subaccount_name' => $subaccountData['subaccount_name'],
                            'description' => $subaccountData['description'],
                            'is_active' => $subaccountData['is_active'] ?? true,
                        ]
                    );

                    if ($subaccount->wasRecentlyCreated) {
                        $createdSubaccounts++;
                    }
                }
            }
        });

        return response()->json([
            'message' => 'Starter accounts synchronized',
            'created_accounts' => $createdAccounts,
            'created_subaccounts' => $createdSubaccounts,
        ]);
    }

    private function starterAccounts(): array
    {
        return [
            [
                'account_number' => '1',
                'account_name' => 'Aset Lancar',
                'description' => 'Kelompok aset lancar utama',
                'subaccounts' => [
                    ['subaccount_number' => '1-10001', 'subaccount_name' => 'Kas', 'description' => 'Kas tunai'],
                    ['subaccount_number' => '1-10002', 'subaccount_name' => 'Bank', 'description' => 'Rekening bank operasional'],
                    ['subaccount_number' => '1-10003', 'subaccount_name' => 'Petty Cash', 'description' => 'Kas kecil operasional'],
                    ['subaccount_number' => '1-10011', 'subaccount_name' => 'Bank Operasional 1', 'description' => 'Rekening bank operasional tambahan'],
                    ['subaccount_number' => '1-10012', 'subaccount_name' => 'Bank Operasional 2', 'description' => 'Rekening bank operasional tambahan'],
                    ['subaccount_number' => '1-10100', 'subaccount_name' => 'Piutang Usaha', 'description' => 'Piutang penjualan'],
                    ['subaccount_number' => '1-10200', 'subaccount_name' => 'Persediaan Barang', 'description' => 'Persediaan barang'],
                    ['subaccount_number' => '1-10300', 'subaccount_name' => 'PPN Masukan', 'description' => 'Pajak masukan yang dapat dikreditkan'],
                    ['subaccount_number' => '1-10403', 'subaccount_name' => 'Uang Muka', 'description' => 'Uang muka pembelian/operasional'],
                ],
            ],
            [
                'account_number' => '2',
                'account_name' => 'Aset Tetap',
                'description' => 'Kelompok aset tetap',
                'subaccounts' => [
                    ['subaccount_number' => '1-10701', 'subaccount_name' => 'Bangunan', 'description' => 'Aset bangunan'],
                    ['subaccount_number' => '1-10703', 'subaccount_name' => 'Kendaraan', 'description' => 'Aset kendaraan'],
                    ['subaccount_number' => '1-10704', 'subaccount_name' => 'Mesin & Peralatan', 'description' => 'Aset mesin dan peralatan'],
                ],
            ],
            [
                'account_number' => '3',
                'account_name' => 'Kewajiban',
                'description' => 'Kelompok kewajiban utama',
                'subaccounts' => [
                    ['subaccount_number' => '2-20100', 'subaccount_name' => 'Hutang Usaha', 'description' => 'Hutang ke supplier'],
                    ['subaccount_number' => '2-20201', 'subaccount_name' => 'Hutang Gaji', 'description' => 'Kewajiban gaji'],
                    ['subaccount_number' => '2-20300', 'subaccount_name' => 'Biaya Masih Harus Dibayar', 'description' => 'Akrual biaya operasional'],
                    ['subaccount_number' => '2-20400', 'subaccount_name' => 'Uang Muka Pelanggan', 'description' => 'DP atau uang muka customer'],
                    ['subaccount_number' => '2-20500', 'subaccount_name' => 'PPN Keluaran', 'description' => 'Kewajiban PPN'],
                ],
            ],
            [
                'account_number' => '4',
                'account_name' => 'Ekuitas',
                'description' => 'Kelompok modal dan laba ditahan',
                'subaccounts' => [
                    ['subaccount_number' => '3-30000', 'subaccount_name' => 'Modal', 'description' => 'Modal disetor'],
                    ['subaccount_number' => '3-30010', 'subaccount_name' => 'Prive', 'description' => 'Pengambilan pribadi pemilik'],
                    ['subaccount_number' => '3-30100', 'subaccount_name' => 'Laba Ditahan', 'description' => 'Saldo laba ditahan'],
                    ['subaccount_number' => '3-30999', 'subaccount_name' => 'Ekuitas Saldo Awal', 'description' => 'Saldo awal ekuitas'],
                ],
            ],
            [
                'account_number' => '5',
                'account_name' => 'Pendapatan',
                'description' => 'Kelompok pendapatan',
                'subaccounts' => [
                    ['subaccount_number' => '4-40000', 'subaccount_name' => 'Pendapatan Penjualan', 'description' => 'Pendapatan utama'],
                    ['subaccount_number' => '4-40010', 'subaccount_name' => 'Pendapatan Jasa', 'description' => 'Pendapatan jasa pemasangan atau servis'],
                    ['subaccount_number' => '4-40100', 'subaccount_name' => 'Diskon Penjualan', 'description' => 'Diskon penjualan'],
                    ['subaccount_number' => '7-70099', 'subaccount_name' => 'Pendapatan Lain-lain', 'description' => 'Pendapatan di luar usaha utama'],
                ],
            ],
            [
                'account_number' => '6',
                'account_name' => 'Harga Pokok & Beban',
                'description' => 'Kelompok HPP dan beban operasional',
                'subaccounts' => [
                    ['subaccount_number' => '5-50000', 'subaccount_name' => 'Harga Pokok Penjualan', 'description' => 'Beban pokok pendapatan'],
                    ['subaccount_number' => '6-60101', 'subaccount_name' => 'Gaji', 'description' => 'Beban gaji'],
                    ['subaccount_number' => '6-60211', 'subaccount_name' => 'Sarana Kantor', 'description' => 'Beban sarana kantor'],
                    ['subaccount_number' => '6-60217', 'subaccount_name' => 'Listrik', 'description' => 'Beban listrik'],
                    ['subaccount_number' => '6-60218', 'subaccount_name' => 'Air & Internet', 'description' => 'Beban utilitas dan internet'],
                    ['subaccount_number' => '6-60300', 'subaccount_name' => 'Sewa', 'description' => 'Beban sewa kantor atau gudang'],
                    ['subaccount_number' => '6-60400', 'subaccount_name' => 'Transport & BBM', 'description' => 'Beban transportasi dan bahan bakar'],
                    ['subaccount_number' => '6-60500', 'subaccount_name' => 'Administrasi Bank', 'description' => 'Biaya administrasi rekening bank'],
                    ['subaccount_number' => '6-60600', 'subaccount_name' => 'Marketing', 'description' => 'Beban iklan dan promosi'],
                    ['subaccount_number' => '8-80999', 'subaccount_name' => 'Beban Lain-lain', 'description' => 'Beban di luar operasional utama'],
                ],
            ],
        ];
    }

    public function initDefaultMappings()
    {
        if (!AccountingSubaccount::query()->exists()) {
            return response()->json([
                'message' => 'Initialize accounts before mappings',
            ], 422);
        }

        $created = $this->seedDefaultMappings();

        return response()->json([
            'message' => 'Default mappings initialized',
            'created' => $created,
        ]);
    }

    public function upsert(Request $request)
    {
        $data = $request->validate([
            'account_number' => ['required', 'string'],
            'account_name' => ['required', 'string'],
            'description' => ['required', 'string'],
            'is_active' => ['required', 'boolean'],
        ]);

        if ($request->filled('id')) {
            $account = AccountingAccount::query()->find($request->input('id'));

            if ($account === null) {
                return response()->json([
                    'message' => 'Account not found',
                ], 404);
            }

            $account->update($data);

            return response()->json($account);
        }

        $account = $this->createAccount($data);

        return response()->json($account);
    }

    public function upsertSubaccount(Request $request)
    {
        $data = $request->validate([
            'accounting_account_id' => ['required', 'integer', 'exists:accounting_accounts,id'],
            'subaccount_number' => ['required', 'string'],
            'subaccount_name' => ['required', 'string'],
            'description' => ['required', 'string'],
            'is_active' => ['required', 'boolean'],
        ]);

        if ($request->filled('id')) {
            $subaccount = AccountingSubaccount::query()->find($request->input('id'));

            if ($subaccount === null) {
                return response()->json([
                    'message' => 'Subaccount not found',
                ], 404);
            }

            $subaccount->update($data);

            return response()->json($subaccount);
        }

        $subaccount = AccountingSubaccount::query()->create($data);

        return response()->json($subaccount);
    }

    private function createAccount(array $data): AccountingAccount
    {
        $account = AccountingAccount::query()->create([
            'account_number' => $data['account_number'],
            'account_name' => $data['account_name'],
            'description' => $data['description'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        foreach ($data['subaccounts'] ?? [] as $subaccount) {
            AccountingSubaccount::query()->create([
                'accounting_account_id' => $account->id,
                'subaccount_number' => $subaccount['subaccount_number'],
                'subaccount_name' => $subaccount['subaccount_name'],
                'description' => $subaccount['description'],
                'is_active' => $subaccount['is_active'] ?? true,
            ]);
        }

        return $account;
    }

    private function seedDefaultMappings(): int
    {
        $defaults = [
            [
                'module' => 'sale',
                'event' => 'receivable',
                'label' => 'Penjualan ke Piutang',
                'description' => 'Piutang usaha saat invoice penjualan dibuat',
                'subaccount_number' => '1-10100',
            ],
            [
                'module' => 'sale',
                'event' => 'revenue',
                'label' => 'Pendapatan Penjualan',
                'description' => 'Pendapatan utama dari invoice penjualan',
                'subaccount_number' => '4-40000',
            ],
            [
                'module' => 'sale',
                'event' => 'cogs',
                'label' => 'Harga Pokok Penjualan',
                'description' => 'HPP saat stok keluar pada transaksi penjualan',
                'subaccount_number' => '5-50000',
            ],
            [
                'module' => 'sale',
                'event' => 'inventory',
                'label' => 'Persediaan Penjualan',
                'description' => 'Persediaan yang berkurang saat penjualan',
                'subaccount_number' => '1-10200',
            ],
            [
                'module' => 'sale_payment',
                'event' => 'receivable',
                'label' => 'Pelunasan Piutang Penjualan',
                'description' => 'Akun piutang yang dikredit saat customer membayar',
                'subaccount_number' => '1-10100',
            ],
            [
                'module' => 'purchase',
                'event' => 'inventory',
                'label' => 'Persediaan Pembelian',
                'description' => 'Persediaan yang bertambah saat invoice pembelian dibuat',
                'subaccount_number' => '1-10200',
            ],
            [
                'module' => 'purchase',
                'event' => 'payable',
                'label' => 'Hutang Usaha Pembelian',
                'description' => 'Hutang usaha yang timbul saat invoice pembelian dibuat',
                'subaccount_number' => '2-20100',
            ],
            [
                'module' => 'purchase_payment',
                'event' => 'payable',
                'label' => 'Pelunasan Hutang Usaha',
                'description' => 'Akun hutang yang didebit saat pembayaran pembelian',
                'subaccount_number' => '2-20100',
            ],
        ];

        $created = 0;

        foreach ($defaults as $default) {
            $subaccount = AccountingSubaccount::query()
                ->where('subaccount_number', $default['subaccount_number'])
                ->first();

            if ($subaccount === null) {
                continue;
            }

            $mapping = AccountingAccountMapping::query()->firstOrCreate(
                [
                    'entity_id' => null,
                    'branch_id' => null,
                    'module' => $default['module'],
                    'event' => $default['event'],
                ],
                [
                    'label' => $default['label'],
                    'description' => $default['description'],
                    'accounting_subaccount_id' => $subaccount->id,
                    'is_active' => true,
                ]
            );

            if ($mapping->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }
}
