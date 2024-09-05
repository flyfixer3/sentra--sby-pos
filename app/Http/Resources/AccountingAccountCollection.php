<?php

namespace App\Http\Resources;


use Illuminate\Http\Resources\Json\ResourceCollection;

class AccountingAccountCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'data' => AccountingAccountCollectionResource::collection($this->collection),
            'total' => $this->collection->count()
        ];
    }

    /**
     * Customize the pagination information for the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  array $paginated
     * @param  array $default
     * @return array
     */
    public function paginationInformation($request, $paginated, $default)
    {
        return [
            'pagination' => [
                'page' => $default['meta']['current_page'],
                'totalPage' => $default['meta']['last_page'],
                'limit' => $default['meta']['per_page']
            ]
        ];
    }
}
