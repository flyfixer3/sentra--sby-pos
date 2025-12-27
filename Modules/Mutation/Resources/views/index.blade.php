@extends('layouts.app')

@section('title', 'Mutations')

@section('third_party_stylesheets')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.25/css/dataTables.bootstrap4.min.css">
@endsection

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item active">Mutations</li>
    </ol>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center justify-content-between">
                        <a href="{{ route('mutations.create') }}" class="btn btn-primary">
                            Add Mutation <i class="bi bi-plus"></i>
                        </a>
                    </div>

                    <hr>

                    {{-- ===== FILTERS (rapih) ===== --}}
                    <div class="row g-2 align-items-end mb-3">
                        <div class="col-md-3">
                            <label class="small text-muted mb-1">Car / Product (keyword)</label>
                            <input id="f_car" type="text" class="form-control form-control-sm"
                                placeholder="ex: Avanza / Brio / Fortuner / kode">
                        </div>

                        <div class="col-md-2">
                            <label class="small text-muted mb-1">Warehouse</label>
                            <select id="f_warehouse" class="form-control form-control-sm">
                                <option value="">-- All --</option>
                                @foreach(($warehouses ?? []) as $wh)
                                    <option value="{{ $wh->id }}">{{ $wh->warehouse_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="small text-muted mb-1">Type</label>
                            <select id="f_type" class="form-control form-control-sm">
                                <option value="">-- All --</option>
                                <option value="In">IN</option>
                                <option value="Out">OUT</option>
                                <option value="Transfer">TRANSFER</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="small text-muted mb-1">Reference</label>
                            <input id="f_ref" type="text" class="form-control form-control-sm"
                                placeholder="ex: TRF-0001 / ADJ-...">
                        </div>

                        <div class="col-md-1">
                            <label class="small text-muted mb-1">From</label>
                            <input id="f_from" type="date" class="form-control form-control-sm">
                        </div>

                        <div class="col-md-1">
                            <label class="small text-muted mb-1">To</label>
                            <input id="f_to" type="date" class="form-control form-control-sm">
                        </div>

                        <div class="col-md-1 d-flex justify-content-end">
                            <div class="d-flex gap-2">
                                <button id="btn_apply"
                                        type="button"
                                        class="btn btn-sm btn-primary d-inline-flex align-items-center justify-content-center btn-icon"
                                        title="Apply filter"
                                        aria-label="Apply filter">
                                    <i class="bi bi-check2"></i>
                                </button>

                                <button id="btn_reset"
                                        type="button"
                                        class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center justify-content-center btn-icon"
                                        title="Reset filter"
                                        aria-label="Reset filter">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                        </div>

                    </div>


                    <div class="table-responsive">
                        {!! $dataTable->table(['class' => 'table table-striped table-bordered w-100'], true) !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('page_scripts')
    {!! $dataTable->scripts() !!}

    <script>
    (function(){
      function dt() {
        return window.LaravelDataTables && window.LaravelDataTables["mutation-table"]
          ? window.LaravelDataTables["mutation-table"]
          : null;
      }

      function bind() {
        var table = dt();
        if(!table) return;

        // inject params setiap request datatable
        table.on('preXhr.dt', function(e, settings, data){
          data.car           = document.getElementById('f_car')?.value || '';
          data.warehouse_id  = document.getElementById('f_warehouse')?.value || '';
          data.mutation_type = document.getElementById('f_type')?.value || '';
          data.reference     = document.getElementById('f_ref')?.value || '';
          data.date_from     = document.getElementById('f_from')?.value || '';
          data.date_to       = document.getElementById('f_to')?.value || '';
        });

        document.getElementById('btn_apply')?.addEventListener('click', function(){
          table.ajax.reload(null, true);
        });

        // Enter di input langsung apply
        ['f_car','f_ref'].forEach(function(id){
          var el = document.getElementById(id);
          if(!el) return;
          el.addEventListener('keydown', function(ev){
            if(ev.key === 'Enter'){
              ev.preventDefault();
              table.ajax.reload(null, true);
            }
          });
        });

        // reset filter
        document.getElementById('btn_reset')?.addEventListener('click', function(){
          document.getElementById('f_car').value = '';
          document.getElementById('f_warehouse').value = '';
          document.getElementById('f_type').value = '';
          document.getElementById('f_ref').value = '';
          document.getElementById('f_from').value = '';
          document.getElementById('f_to').value = '';
          table.ajax.reload(null, true);
        });
      }

      document.addEventListener('DOMContentLoaded', function(){
        // Yajra kadang init agak lambat -> retry beberapa kali
        var tries = 0;
        var timer = setInterval(function(){
          tries++;
          if(dt()){
            clearInterval(timer);
            bind();
          }
          if(tries > 20) clearInterval(timer); // stop after ~2s
        }, 100);
      });
    })();
    </script>
@endpush
