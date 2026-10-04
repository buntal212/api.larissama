<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

class ApiPagination
{
    /**
     * Paginate a query without calculating an SQL offset for pages past the end.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return LengthAwarePaginator<int, TModel>
     */
    public static function paginate(Builder $query, int $perPage, int $page): LengthAwarePaginator
    {
        if ($perPage < 1 || $page < 1) {
            throw new \InvalidArgumentException('Pagination values must be positive integers.');
        }

        $total = (int) (clone $query)->toBase()->getCountForPagination();
        $lastPage = max(1, (int) ceil($total / $perPage));

        $items = $page > $lastPage
            ? $query->getModel()->newCollection()
            : (clone $query)->forPage($page, $perPage)->get();

        return new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => 'page',
        ]);
    }
}
