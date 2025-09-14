<?php

namespace App\Http\Controllers\API\Reports;

use Throwable;
use App\Models\Event;
use App\Models\Order;
use App\Models\EventOrganizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Enum\Status\OrderStatusEnum;
use App\Models\FinancialTransaction;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\BaseController;
use App\Enum\Type\FinancialTransactionTypeEnum;

class FinancialReportController extends BaseController
{
    public function showEventReport(Event $event)
    {
        $this->authorize('viewFinancialReport', $event);

        try {
            $metrics = $this->calculateEventMetrics($event);

            $reportData = [
                'event' => [
                    'id' => $event->id,
                    'name' => $event->name,
                ],
                'summary' => $metrics,
            ];

            return $this->sendResponse($reportData, 'Event financial report retrieved successfully.');
        } catch (Throwable $e) {
            return $this->sendError('Failed to generate event report.', ['error' => $e->getMessage()], 500);
        }
    }

    public function showEoReport(EventOrganizer $eventOrganizer)
    {
        $this->authorize('viewFinancialReport', $eventOrganizer);

        try {
            $totalMetrics = [
                'total_income' => 0,
                'total_expenses' => 0,
                'net_profit' => 0,
                'tickets_sold' => 0,
                'ticket_sales' => 0,
                'other_income' => 0,
            ];
            $eventBreakdowns = [];

            $events = $eventOrganizer->events()->get();

            foreach ($events as $event) {
                $metrics = $this->calculateEventMetrics($event);

                // Akumulasi total
                foreach ($metrics as $key => $value) {
                    $totalMetrics[$key] += $value;
                }

                $eventBreakdowns[] = [
                    'id' => $event->id,
                    'name' => $event->name,
                    'summary' => $metrics,
                ];
            }

            $reportData = [
                'event_organizer' => ['id' => $eventOrganizer->id, 'name' => $eventOrganizer->name],
                'summary' => $totalMetrics,
                'event_breakdowns' => $eventBreakdowns,
            ];

            return $this->sendResponse($reportData, 'EO financial report retrieved successfully.');
        } catch (Throwable $e) {
            return $this->sendError('Failed to generate EO report.', ['error' => $e->getMessage()], 500);
        }
    }

    public function showGlobalReport()
    {
        try {
            $totalMetrics = [
                'total_income' => 0,
                'total_expenses' => 0,
                'net_profit' => 0,
                'tickets_sold' => 0,
                'ticket_sales' => 0,
                'other_income' => 0,
            ];
            $eoBreakdowns = [];

            $organizers = EventOrganizer::with('events')->get();

            foreach ($organizers as $organizer) {
                $eoSubtotal = ['net_profit' => 0, 'tickets_sold' => 0]; // Simpan subtotal per EO

                foreach ($organizer->events as $event) {
                    $metrics = $this->calculateEventMetrics($event);
                    foreach ($metrics as $key => $value) {
                        $totalMetrics[$key] += $value;
                    }
                    $eoSubtotal['net_profit'] += $metrics['net_profit'];
                    $eoSubtotal['tickets_sold'] += $metrics['tickets_sold'];
                }

                $eoBreakdowns[] = [
                    'id' => $organizer->id,
                    'name' => $organizer->name,
                    'total_events' => $organizer->events->count(),
                    'subtotal_net_profit' => $eoSubtotal['net_profit'],
                    'subtotal_tickets_sold' => $eoSubtotal['tickets_sold'],
                ];
            }

            $reportData = [
                'summary' => $totalMetrics,
                'eo_breakdowns' => $eoBreakdowns,
            ];

            return $this->sendResponse($reportData, 'Global financial report retrieved successfully.');
        } catch (Throwable $e) {
            return $this->sendError('Failed to generate global report.', ['error' => $e->getMessage()], 500);
        }
    }

    private function calculateEventMetrics(Event $event): array
    {
        $ticketSales = $event->orders()->where('status', OrderStatusEnum::PAID->value)->sum('net_amount');
        $otherIncome = $event->financialTransactions()->where('type', FinancialTransactionTypeEnum::INCOME->value)->sum('amount');
        $totalExpenses = $event->financialTransactions()->where('type', FinancialTransactionTypeEnum::EXPENSE->value)->sum('amount');

        $ticketsSold = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.event_id', $event->id)
            ->where('orders.status', OrderStatusEnum::PAID->value)
            ->sum('order_items.quantity');

        $totalIncome = $ticketSales + $otherIncome;
        $netProfit = $totalIncome - $totalExpenses;

        return [
            'total_income' => (float) $totalIncome,
            'total_expenses' => (float) $totalExpenses,
            'net_profit' => (float) $netProfit,
            'tickets_sold' => (int) $ticketsSold,
            'ticket_sales' => (float) $ticketSales,
            'other_income' => (float) $otherIncome,
        ];
    }
}
