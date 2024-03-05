<div>
    <div class="form-row">
        <!-- <div class="col-md-4">
            <div class="form-group">
                <label>Search Product</label>
                <input type="text" wire:model="search" class="form-control" >
            </div>
        </div> -->
        <div class="col-md-7">
            <div class="form-group">
                <label>Product Category</label>
                <select wire:model="category" class="form-control">
                    <option value="">All Products</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->category_code }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="col-md-5">
            <div class="form-group">
                <label>Product Count</label>
                <select wire:model="showCount" class="form-control">
                    <option value="9">9 Products</option>
                    <option value="15">15 Products</option>
                    <option value="21">21 Products</option>
                    <option value="30">30 Products</option>
                    <option value="">All Products</option>
                </select>
            </div>
        </div>
    </div>
</div>
