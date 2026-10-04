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
    public static function paginate(Builder $query, int $perPage, int|string $page): LengthAwarePaginator
    {
        $page = self::normalizePage($page);

        if ($perPage < 1) {
            throw new \InvalidArgumentException('Pagination values must be positive integers.');
        }

        $total = (int) (clone $query)->toBase()->getCountForPagination();
        $lastPage = max(1, intdiv($total, $perPage) + ($total % $perPage === 0 ? 0 : 1));
        $pageIsAfterLast = self::comparePositiveIntegers($page, (string) $lastPage) > 0;
        $nativePage = self::exceedsPhpIntegerRange($page) ? 1 : (int) $page;

        $items = $pageIsAfterLast
            ? $query->getModel()->newCollection()
            : (clone $query)->forPage($nativePage, $perPage)->get();

        return new LengthAwarePaginator($items, $total, $perPage, $nativePage, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => 'page',
        ]);
    }

    public static function normalizePage(int|string $page): string
    {
        $digits = (string) $page;

        if (preg_match('/\A[0-9]+\z/', $digits) !== 1) {
            throw new \InvalidArgumentException('Page must be a positive integer.');
        }

        $normalized = ltrim($digits, '0');

        if ($normalized === '') {
            throw new \InvalidArgumentException('Page must be a positive integer.');
        }

        return $normalized;
    }

    public static function exceedsPhpIntegerRange(string $page): bool
    {
        return self::comparePositiveIntegers($page, (string) PHP_INT_MAX) > 0;
    }

    private static function comparePositiveIntegers(string $left, string $right): int
    {
        $lengthComparison = strlen($left) <=> strlen($right);

        return $lengthComparison !== 0 ? $lengthComparison : strcmp($left, $right);
    }
}
