<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Resources\Pages;

use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Base page for API-backed Filament resources that use ->records() instead of Eloquent.
 *
 * ListRecords::makeTable() and InteractsWithTable::makeTable() both wrap
 * getTableQuery() in a ->query() closure. When getTableQuery() calls
 * Resource::getEloquentQuery() it crashes because there is no Eloquent model.
 *
 * This class:
 * 1. Overrides getTableQuery() to return null (no Eloquent query)
 * 2. Overrides makeTable() to skip ListRecords' non-nullable Builder closure
 *
 * The table uses the ->records() data source defined in the resource instead.
 */
class ApiListRecords extends ListRecords
{
    protected function getTableQuery(): Builder|Relation|null
    {
        return null;
    }

    protected function makeTable(): Table
    {
        $table = $this->makeBaseTable();

        static::getResource()::configureTable($table);

        return $table;
    }
}
