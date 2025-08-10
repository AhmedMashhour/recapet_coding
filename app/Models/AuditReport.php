<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditReport extends Model
{
    protected $fillable = [
        'report_id',
        'audit_date',
        'parameters',
        'summary',
        'errors',
        'warnings',
        'details',
        'status',
    ];

    protected $casts = [
        'audit_date' => 'datetime',
        'parameters' => 'array',
        'summary' => 'array',
        'errors' => 'array',
        'warnings' => 'array',
        'details' => 'array',
    ];

    public function getTotalErrorsAttribute(): int
    {
        return $this->summary['total_errors'] ?? 0;
    }

    public function getTotalWarningsAttribute(): int
    {
        return $this->summary['total_warnings'] ?? 0;
    }

    public function getTotalDiscrepancyAttribute(): float
    {
        return $this->summary['total_discrepancy'] ?? 0;
    }

    public function getHasIssuesAttribute(): bool
    {
        return $this->total_errors > 0 || $this->total_warnings > 0;
    }
}
