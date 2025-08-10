<?php

namespace App\Filament\Pages;

use App\Models\AuditReport;
use Filament\Pages\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Carbon\Carbon;

class FinancialAudit extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-magnifying-glass';

    protected static ?string $navigationGroup = 'Audit & Reports';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.financial-audit';

    public ?array $data = [];

    public $auditResults = null;

    public $isRunning = false;

    public $currentReportId = null;

    public function mount(): void
    {
        $this->form->fill([
            'detailed' => false,
            'wallet' => null,
            'from' => null,
            'to' => null,
        ]);

        $this->loadLastAuditResults();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Audit Parameters')
                    ->description('Configure the audit parameters before running the financial audit.')
                    ->schema([
                        Forms\Components\Toggle::make('detailed')
                            ->label('Show Detailed Results')
                            ->helperText('Display detailed breakdown of all issues found during the audit'),

                        Forms\Components\Select::make('wallet')
                            ->label('Specific Wallet')
                            ->options(function () {
                                return \App\Models\Wallet::query()
                                    ->orderBy('wallet_number')
                                    ->pluck('wallet_number', 'id');
                            })
                            ->searchable()
                            ->preload()
                            ->placeholder('All Wallets')
                            ->helperText('Leave empty to audit all wallets'),

                        Forms\Components\DatePicker::make('from')
                            ->label('Start Date')
                            ->maxDate(now())
                            ->native(false),

                        Forms\Components\DatePicker::make('to')
                            ->label('End Date')
                            ->maxDate(now())
                            ->native(false)
                            ->after('from'),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function runAudit(): void
    {
        $this->validate();

        $this->isRunning = true;

        try {
            // Create a new report ID
            $reportId = 'audit_' . now()->format('Y-m-d_H-i-s') . '_' . Str::random(6);

            $command = 'wallet:audit';
            $parameters = [
                '--report-id' => $reportId,
            ];

            if ($this->data['detailed']) {
                $parameters['--detailed'] = true;
            }

            if ($this->data['wallet']) {
                $parameters['--wallet'] = $this->data['wallet'];
            }

            if ($this->data['from']) {
                $parameters['--from'] = $this->data['from'];
            }

            if ($this->data['to']) {
                $parameters['--to'] = $this->data['to'];
            }

            // Run the audit command
            $exitCode = Artisan::call($command, $parameters);

            // Try to load from database first
            $report = AuditReport::where('report_id', $reportId)->first();

            if (!$report) {
                // Fallback to file storage
                sleep(1); // Give time for file to be written
                $this->loadLastAuditResults();
            } else {
                // Load from database
                $this->currentReportId = $report->id;
                $this->auditResults = [
                    'audit_date' => $report->audit_date->toIso8601String(),
                    'summary' => $report->summary,
                    'errors' => $report->errors ?? [],
                    'warnings' => $report->warnings ?? [],
                ];
            }

            if ($exitCode === 0) {
                Notification::make()
                    ->title('Audit Completed Successfully')
                    ->success()
                    ->body('No discrepancies found in the financial audit.')
                    ->send();
            } else {
                $errorCount = $this->auditResults['summary']['total_errors'] ?? 0;
                $warningCount = $this->auditResults['summary']['total_warnings'] ?? 0;

                Notification::make()
                    ->title('Audit Completed with Issues')
                    ->warning()
                    ->body("Found {$errorCount} errors and {$warningCount} warnings. Please review the results.")
                    ->send();
            }

        } catch (\Exception $e) {
            Notification::make()
                ->title('Audit Failed')
                ->danger()
                ->body('An error occurred while running the audit: ' . $e->getMessage())
                ->send();
        } finally {
            $this->isRunning = false;
        }
    }

    protected function loadLastAuditResults(): void
    {
        try {
            // Try to load from database first
            $latestReport = AuditReport::latest('audit_date')->first();

            if ($latestReport) {
                $this->currentReportId = $latestReport->id;
                $this->auditResults = [
                    'audit_date' => $latestReport->audit_date->toIso8601String(),
                    'summary' => $latestReport->summary,
                    'errors' => $latestReport->errors ?? [],
                    'warnings' => $latestReport->warnings ?? [],
                ];
                return;
            }

            // Fallback to file storage
            clearstatcache();

            $files = collect(Storage::disk('local')->files('logs'))
                ->filter(fn ($file) => str_starts_with(basename($file), 'audit_report_'))
                ->sortByDesc(fn ($file) => Storage::disk('local')->lastModified($file));

            if ($files->isNotEmpty()) {
                $content = Storage::disk('local')->get($files->first());
                $decoded = json_decode($content, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    $this->auditResults = $decoded;
                }
            }
        } catch (\Exception $e) {
            \Log::error('Error loading audit results: ' . $e->getMessage());
            $this->auditResults = null;
        }
    }

    public function refreshResults(): void
    {
        $this->loadLastAuditResults();

        if ($this->auditResults) {
            Notification::make()
                ->title('Results Refreshed')
                ->success()
                ->body('Audit results have been reloaded.')
                ->send();
        } else {
            Notification::make()
                ->title('No Results Found')
                ->warning()
                ->body('No audit results available. Please run an audit first.')
                ->send();
        }
    }

    #[Computed]
    public function formattedAuditDate(): ?string
    {
        if (!$this->auditResults) {
            return null;
        }

        return Carbon::parse($this->auditResults['audit_date'])->format('F j, Y g:i A');
    }

    #[Computed]
    public function errorsByType(): array
    {
        if (!$this->auditResults || empty($this->auditResults['errors'])) {
            return [];
        }

        $grouped = collect($this->auditResults['errors'])->groupBy('type');

        return $grouped->map(function ($errors, $type) {
            return [
                'type' => $this->formatErrorType($type),
                'count' => $errors->count(),
                'total_amount' => $errors->sum('difference') ?? $errors->sum('amount') ?? 0,
                'errors' => $errors->toArray(),
            ];
        })->toArray();
    }

    #[Computed]
    public function warningsByType(): array
    {
        if (!$this->auditResults || empty($this->auditResults['warnings'])) {
            return [];
        }

        return collect($this->auditResults['warnings'])->groupBy('type')->toArray();
    }

    protected function formatErrorType(string $type): string
    {
        return str($type)->replace('_', ' ')->title()->toString();
    }

    public function downloadReport(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        if (!$this->auditResults) {
            Notification::make()
                ->title('No Report Available')
                ->warning()
                ->body('Please run an audit first.')
                ->send();

            return response()->stream();
        }

        $filename = 'financial_audit_report_' . now()->format('Y-m-d_H-i-s') . '.json';

        return response()->streamDownload(function () {
            echo json_encode($this->auditResults, JSON_PRETTY_PRINT);
        }, $filename);
    }

    public function viewPreviousReports(): void
    {
        // This could redirect to a table view of all audit reports
        redirect()->route('filament.admin.resources.audit-reports.index');
    }
}
