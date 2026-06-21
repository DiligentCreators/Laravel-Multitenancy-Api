<?php

namespace App\Services\Central;

use App\Models\Invoice;
use App\Models\Module;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

class DashboardAnalyticsService
{
    protected const CACHE_TTL = 3600;

    /**
     * @return array<string, mixed>
     */
    public function getCachedAnalytics(): array
    {
        return Cache::tags(['analytics', 'dashboard'])->remember('dashboard.analytics', self::CACHE_TTL, function () {
            return $this->computeAnalytics();
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function computeAnalytics(): array
    {
        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $yearStart = $now->copy()->startOfYear();
        $lastMonthStart = $now->copy()->subMonth()->startOfMonth();
        $lastMonthEnd = $now->copy()->subMonth()->endOfMonth();

        $activeSubscriptions = Subscription::whereIn('status', ['active', 'trial'])
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->count();

        $totalTenants = Tenant::count();

        $mrr = Invoice::where('status', 'paid')
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->sum('total_amount');

        $arr = $mrr * 12;

        $lastMonthMrr = Invoice::where('status', 'paid')
            ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])
            ->sum('total_amount');

        $mrrGrowthRate = $lastMonthMrr > 0 ? round(($mrr - $lastMonthMrr) / $lastMonthMrr * 100, 2) : 0;

        $churnedThisMonth = Subscription::where('status', 'cancelled')
            ->whereBetween('updated_at', [$monthStart, $now])
            ->count();

        $totalEverSubscribed = Subscription::distinct('tenant_id')->count('tenant_id');
        $churnRate = $totalEverSubscribed > 0
            ? round($churnedThisMonth / max($totalEverSubscribed, 1) * 100, 2)
            : 0;

        $revenueByPlan = Subscription::whereIn('status', ['active', 'trial'])
            ->selectRaw('plan_id, count(*) as count')
            ->groupBy('plan_id')
            ->with('plan:id,name,monthly_price')
            ->get()
            ->map(fn ($s) => [
                'plan_id' => $s->plan_id,
                'plan_name' => $s->plan?->name,
                'tenant_count' => $s->count,
                'monthly_revenue' => ($s->plan?->monthly_price ?? 0) * $s->count,
            ]);

        $totalRevenueYtd = Invoice::where('status', 'paid')
            ->whereBetween('created_at', [$yearStart, $now])
            ->sum('total_amount');

        $pendingInvoices = Invoice::where('status', 'overdue')->count();
        $pendingRevenue = Invoice::where('status', 'overdue')->sum('total_amount');

        $subscriptionsExpiring = Subscription::whereIn('status', ['active', 'trial'])
            ->whereNotNull('ends_at')
            ->where('ends_at', '>=', $now)
            ->where('ends_at', '<=', $now->copy()->addDays(7))
            ->count();

        $avgRevenuePerTenant = $totalTenants > 0 ? round($mrr / $totalTenants, 2) : 0;

        return [
            'mrr' => round($mrr, 2),
            'arr' => round($arr, 2),
            'mrr_growth_rate' => $mrrGrowthRate,
            'total_tenants' => $totalTenants,
            'active_subscriptions' => $activeSubscriptions,
            'churn_rate' => $churnRate,
            'churned_this_month' => $churnedThisMonth,
            'revenue_by_plan' => $revenueByPlan,
            'total_revenue_ytd' => round($totalRevenueYtd, 2),
            'pending_invoices' => $pendingInvoices,
            'pending_revenue' => round($pendingRevenue, 2),
            'subscriptions_expiring_soon' => $subscriptionsExpiring,
            'avg_revenue_per_tenant' => $avgRevenuePerTenant,
            'cached_at' => $now->toDateTimeString(),
        ];
    }

    public function refreshCache(): void
    {
        Cache::tags(['analytics', 'dashboard'])->forget('dashboard.analytics');
        $this->getCachedAnalytics();
    }

    public function getModuleUsageStats(): array
    {
        return Cache::tags(['analytics'])->remember('dashboard.module_usage', self::CACHE_TTL, function () {
            $modules = Module::withCount(['tenants' => fn ($q) => $q->where('is_active', true)])
                ->get()
                ->map(fn ($m) => [
                    'name' => $m->name,
                    'slug' => $m->slug,
                    'active_tenants' => $m->tenants_count,
                ]);

            return ['modules' => $modules];
        });
    }
}
