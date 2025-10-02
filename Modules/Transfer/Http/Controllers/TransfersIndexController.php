<?php

namespace Modules\Transfer\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Transfer\DataTables\OutgoingTransfersDataTable;
use Modules\Transfer\DataTables\IncomingTransfersDataTable;

class TransfersIndexController extends Controller
{
    /**
     * Ambil ID cabang aktif sesuai pola project (session 'active_branch').
     */
    protected function activeBranchId(): ?int
    {
        return session('active_branch') ?? (auth()->user()->default_branch_id ?? null);
    }

    /** Halaman index dengan 2 tab (Dikirim & Diterima) */
    public function index(
        Request $request,
        OutgoingTransfersDataTable $outgoingDataTable,
        IncomingTransfersDataTable $incomingDataTable
    ) {
        $activeTab = $request->get('tab', 'outgoing');
        if (!in_array($activeTab, ['outgoing', 'incoming'])) $activeTab = 'outgoing';

        return view('transfer::index', [
            'activeTab'     => $activeTab,
            'outgoingTable' => $outgoingDataTable->html(),
            'incomingTable' => $incomingDataTable->html(),
        ]);
    }

    public function outgoing(
        OutgoingTransfersDataTable $outgoingDataTable,
        IncomingTransfersDataTable $incomingDataTable
    ) {
        return view('transfer::index', [
            'activeTab'     => 'outgoing',
            'outgoingTable' => $outgoingDataTable->html(),
            'incomingTable' => $incomingDataTable->html(),
        ]);
    }

    public function incoming(
        OutgoingTransfersDataTable $outgoingDataTable,
        IncomingTransfersDataTable $incomingDataTable
    ) {
        return view('transfer::index', [
            'activeTab'     => 'incoming',
            'outgoingTable' => $outgoingDataTable->html(),
            'incomingTable' => $incomingDataTable->html(),
        ]);
    }

    /** ====== Endpoint JSON (AJAX) untuk DataTables ====== */
    public function outgoingData(OutgoingTransfersDataTable $outgoingDataTable)
    {
        return $outgoingDataTable->ajax();
    }

    public function incomingData(IncomingTransfersDataTable $incomingDataTable)
    {
        return $incomingDataTable->ajax();
    }
}
