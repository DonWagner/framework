<?php

namespace Shared\DataTable;

use Nova\Database\ORM\Builder as ModelBuilder;
use Nova\Database\Query\Builder as QueryBuilder;
use Nova\Http\Request;
use Nova\Support\Arr;
use Nova\Support\Str;

use Closure;
use InvalidArgumentException;


class DataTable
{
    /**
     * @var Shared\DataTable\Factory
     */
    protected $factory;

    /**
     * @var array
     */
    protected $columns = array();

    /**
     * @var array
     */
    protected $groupStack = array();


    /**
     * Server Side Processor for DataTables.
     *
     * @param Nova\Database\Query\Builder|Nova\Database\ORM\Builder $query
     * @param array $columns
     *
     * @return array
     */
    public function __construct(Factory $factory, array $columns)
    {
        $this->factory = $factory;

        foreach ($columns as $column) {
            if (is_array($column) && ! is_null($name = Arr::get($column, 'name'))) {
                $this->column($name, $column);
            }
        }
    }

    /**
     * Create a column group with shared attributes.
     *
     * @param  array     $attributes
     * @param  \Closure  $callback
     * @return void
     */
    public function group(array $attributes, Closure $callback)
    {
        if (! empty($this->groupStack)) {
            $lastGroup = last($this->groupStack);

            $attributes = array_merge($lastGroup, $attributes);
        }

        $this->groupStack[] = $attributes;

        call_user_func($callback, $this);

        array_pop($this->groupStack);
    }

    /**
     * Adds a column definition to internal options.
     *
     * @param string  $name
     * @param string|array|\Closure|null  $attributes
     *
     * @return array
     */
    public function column($name, $attributes = null)
    {
        if (isset($this->columns[$name])) {
            throw new InvalidArgumentException('Column already exists.');
        }

        // Check if the column name is valid.
        else if (preg_match('/^[a-z]\w+/i', $name) !== 1) {
            throw new InvalidArgumentException('Invalid column name.');
        }

        if ($attributes instanceof Closure) {
            $attributes = array('uses' => $attributes);
        }

        //
        else if (! is_array($attributes)) {
            $attributes = array('data' => $attributes);
        }

        $attributes['name'] = $name;

        if (empty($data = Arr::get($attributes, 'data'))) {
            $attributes['data'] = str_replace('.', '_', $name);
        }

        // Check if the column data is valid.
        else if (preg_match('/^[a-z]\w+/i', $data) !== 1) {
            throw new InvalidArgumentException('Invalid column data.');
        }

        if (! isset($attributes['uses']) && ! is_null($callback = $this->findColumnClosure($attributes))) {
            $attributes['uses'] = $callback;
        }

        if (! empty($this->groupStack)) {
            $lastGroup = last($this->groupStack);

            $attributes = array_merge($lastGroup, $attributes);
        }

        $this->columns[$name] = $column = new Column($attributes);

        return $column;
    }

    /**
     * Find the Closure in an column array or returns a default callback.
     *
     * @param  array  $column
     * @return \Closure
     */
    protected function findColumnClosure(array $column)
    {
        return Arr::first($column, function ($key, $value)
        {
            return is_callable($value) && is_numeric($key);
        });
    }

    public function script()
    {
        $lines = array();

        foreach ($this->columns as $name => $column) {
            $orderable  = $column->get('orderable', false)  ? 'true' : 'false';
            $searchable = $column->get('searchable', false) ? 'true' : 'false';

            $lines[] = sprintf("{ data: '%s', name: '%s', orderable: %s, searchable: %s, className: '%s' }",
                $column->get('data'), $name, $orderable,  $searchable, $column->get('className')
            );
        }

        return implode(', ', $lines);
    }

    /**
     * Handle a Request.
     *
     * @param Nova\Database\Query\Builder|Nova\Database\ORM\Builder $query
     * @param Nova\Http\Request $request
     *
     * @return array
     */
    public function handle($query, Request $request = null)
    {
        if ($query instanceof ModelBuilder) {
            $queryBuilder = $query->getQuery();

            if (is_null($queryBuilder->columns)) {
                $table = $query->getModel()->getTable();

                $query->select($table .'.*');
            }
        }

        //
        else if (! $query instanceof QueryBuilder) {
            throw new InvalidArgumentException('Invalid query.');
        }

        if (is_null($request)) {
            $request = $this->getRequest();
        }

        $input = $request->only('draw', 'columns', 'start', 'length', 'search', 'order');

        // Get the columns from input.
        $columns = array_filter($columns = Arr::get($input, 'columns', array()), function ($column)
        {
            return is_array($column) && ! empty($column);
        });

        //
        // Compute the draw.

        $draw = (int) Arr::get($input, 'draw', 0);

        //
        // Compute the total count.

        $recordsTotal = $query->count();

        //
        // Handle the global searching.

        $value = Arr::get($input, 'search.value', '');

        if ($this->validSearchValue($value = trim($value))) {
            $query->where(function ($query) use ($columns, $value)
            {
                foreach ($columns as $column) {
                    $searchable = Arr::get($column, 'searchable', 'false');

                    if (($searchable === 'true') && ! is_null($field = Arr::get($column, 'name'))) {
                        $this->columnSearch($query, $field, $value, 'or');
                    }
                }
            });
        }

        //
        // Handle the column searching.

        foreach ($columns as $column) {
            $searchable = Arr::get($column, 'searchable', 'false');

            if (($searchable === 'true') && ! is_null($field = Arr::get($column, 'name'))) {
                $value = Arr::get($column, 'search.value', '');

                if ($this->validSearchValue($value = trim($value))) {
                    $this->columnSearch($query, $field, $value, 'and');
                }
            }
        }

        //
        // Compute the filtered count.

        $recordsFiltered = $query->count();

        //
        // Handle the column ordering.

        $orders = array_filter($orders = Arr::get($input, 'order', array()), function ($order)
        {
            return is_array($order) && isset($order['column']) && isset($order['dir']);
        });

        foreach ($orders as $order) {
            $key = (int) Arr::get($order, 'column', -1);

            $column = (($key !== -1) && isset($columns[$key])) ? $columns[$key] : array();

            $orderable = Arr::get($column, 'orderable', 'false');

            if (($orderable === 'true') && ! is_null($field = Arr::get($column, 'name'))) {
                $direction = ($order['dir'] === 'asc') ? 'ASC' : 'DESC';

                $this->columnOrdering($query, $field, $direction);
            }
        }

        //
        // Handle the results pagination.

        $start  = Arr::get($input, 'start',  0);
        $length = Arr::get($input, 'length', 25);

        $query->skip($start)->take($length);

        //
        // Retrieve the results from database.

        $results = $query->get();

        //
        // Format the results according with DataTables specifications.

        $data = $results->map(function ($result)
        {
            return $this->createRecord($result);

        })->toArray();

        //
        // Create and return a JSON Response instance.

        return $this->createResponse(
            compact('draw', 'recordsTotal', 'recordsFiltered', 'data')
        );
    }

    /**
     * Handles the search for a column.
     *
     * @param Nova\Database\Query\Builder|Nova\Database\ORM\Builder $query
     * @param string $field
     * @param string $search
     * @param string $boolean
     *
     * @return void
     */
    protected function columnSearch($query, $field, $search, $boolean = 'and')
    {
        if (($query instanceof ModelBuilder) && Str::contains($field, '.')) {
            list ($relation, $field) = explode('.', $field, 2);

            return $query->has($relation, '>=', 1, $boolean, function ($query) use ($field, $search)
            {
                $query->where($field, 'like', '%' .$search .'%');
            });
        }

        $query->where($field, 'like', '%' .$search .'%', $boolean);
    }

    /**
     * Handles the search for a column.
     *
     * @param Nova\Database\Query\Builder|Nova\Database\ORM\Builder $query
     * @param string $field
     * @param string $direction
     *
     * @return void
     */
    protected function columnOrdering($query, $field, $direction)
    {
        if (($query instanceof ModelBuilder) && Str::contains($field, '.')) {
            list ($relation, $column) = explode('.', $field, 2);

            //
            $relation = $query->getRelation($relation);

            $table = with($related = $relation->getRelated())->getTable();

            $hasQuery = $relation->getRelationCountQuery($related->newQuery(), $query);

            //
            $column = with($grammar = $query->getGrammar())->wrap($table .'.' .$column);

            $sql = str_replace('count(*)', 'group_concat(distinct ' .$column .')', $hasQuery->toSql());

            $field = str_replace('.', '_', $field) .'_order';

            $query->selectRaw('('. $sql .') as ' .$grammar->wrap($field), $hasQuery->getBindings());
        }

        $query->orderBy($field, $direction);
    }

    /**
     * Builds a record from a query's result.
     *
     * @param mixed $result
     *
     * @return array
     */
    protected function createRecord($result)
    {
        $record = array();

        foreach ($this->columns as $name => $column) {
            $key = $column->get('data');

            $callback = $column->get('uses', function ($record, $field)
            {
                if (! Str::contains($field, '.')) {
                    return $record->{$field};
                }
            });

            if ($callback instanceof Closure) {
                $record[$key] = call_user_func($callback, $result, $name);
            }
        }

        return $record;
    }

    /**
     * Returns the column options.
     *
     * @param array $data
     * @param int $status
     * @param array $headers
     * @param int $options
     *
     * @return \Nova\Http\JsonResponse
     */
    protected function createResponse(array $data, $status = 200, array $headers = array(), $options = 0)
    {
        $responseFactory = $this->getResponseFactory();

        return $responseFactory->json($data, $status, $headers, $options);
    }

    /**
     * Validate the given string for validity as search query.
     *
     * @param string $value
     *
     * @return bool
     */
    protected function validSearchValue($value)
    {
        return preg_match('/^[\p{L}\p{M}\p{N}\p{P}\p{Zs}_-]+$/u', $value);
    }

    /**
     * Returns the Request instance.
     *
     * @return \Nova\Http\Request
     */
    protected function getRequest()
    {
        return $this->factory->getRequest();
    }

    /**
     * Returns the Response Factory instance.
     *
     * @return \Nova\Routing\ResponseFactory
     */
    protected function getResponseFactory()
    {
        return $this->factory->getResponseFactory();
    }

    /**
     * Returns the DataTable Factory instance.
     *
     * @return \Shared\DataTable\Factory
     */
    protected function getFactory()
    {
        return $this->query;
    }

    /**
     * Returns the options.
     *
     * @return array
     */
    protected function getColumns()
    {
        return array_values($this->columns);
    }
}
