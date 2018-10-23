<?php
/**
 * Created by PhpStorm.
 * User: solutioneden
 * Date: 2017/8/21
 * Time: 22:28
 */

namespace App\Traits;


use Illuminate\Database\Eloquent\Builder;


/**
 * Trait SearchableTrait
 * @property array $keywordColumns
 * @property array $searchableColumns
 * @package App\Traits
 * @method Builder search()
 */
trait SearchableTrait
{
    protected $emptyStr = ['null',
        '',
        'undefined',];

    public function scopeSearch(Builder $q)
    {
        $request = request();
        if ($request->has('keyword') && false === array_search($request->keyword, $this->emptyStr)) {
            $q->where(function ($query) {
                $keyword = request()->keyword;

                foreach ($this->keywordColumns as $key => $column) {
                    if ($key == 0) {
                        $query->where($query->qualifyColumn($column), 'like', "%{$keyword}%");
                    } else {
                        $query->orWhere($query->qualifyColumn($column), 'like', "%{$keyword}%");
                    }
                }
            });
        }

        if ($this->searchableColumns) {
            $searchTerms = $request->only(array_keys($this->searchableColumns));
            foreach ($searchTerms as $key => $value) {
                if (false === array_search($value, $this->emptyStr)) {
                    $q->where($q->qualifyColumn($key), 'like', "{$value}");
                }
            }
        }


        return $q;
    }
}