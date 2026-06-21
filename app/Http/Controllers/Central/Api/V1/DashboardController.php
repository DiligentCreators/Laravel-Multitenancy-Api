<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CentralUser;
use App\Models\Feature;
use App\Models\Module;
use App\Models\OverageCharge;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $totalTenants = Tenant::withTrashed()->count();
        $activeTenants = Tenant::whereHas('activeSubscription')->count();
        $trialTenants = Subscription::where('status', 'trial')
            ->where(function ($q) {
                $q->where('ends_at', '>=', now())->orWhereNull('ends_at');
            })
            ->count();
        $suspendedTenants = Subscription::where('status', 'suspended')->count();
        $expiredTenants = Subscription::where('status', 'expired')->count();

        $totalUsers = CentralUser::withTrashed()->count();
        $activeUsers = CentralUser::whereNull('deleted_at')->where('is_suspended', false)->count();

        $totalPlans = Plan::withTrashed()->count();
        $activePlans = Plan::where('is_active', true)->count();

        $totalSubscriptions = Subscription::withTrashed()->count();
        $activeSubscriptions = Subscription::active()->count();
        $newSubscriptions = Subscription::where('created_at', '>=', now()->subMonth())->count();
        $cancelledSubscriptions = Subscription::where('status', 'cancelled')->count();
        $expiringSoon = Subscription::active()
            ->where('ends_at', '<=', now()->addDays(7))
            ->count();

        $mrr = Subscription::where('status', 'active')
            ->where(function ($q) {
                $q->where('ends_at', '>=', now())->orWhereNull('ends_at');
            })
            ->join('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->where('subscriptions.billing_cycle', 'monthly')
            ->sum('plans.monthly_price');

        $arr = Subscription::where('status', 'active')
            ->where(function ($q) {
                $q->where('ends_at', '>=', now())->orWhereNull('ends_at');
            })
            ->join('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->where('subscriptions.billing_cycle', 'yearly')
            ->sum('plans.yearly_price');

        $annualMrr = (float) $mrr * 12;
        $totalArr = $annualMrr + (float) $arr;

        $totalFeatures = Feature::withTrashed()->count();
        $activeFeatures = Feature::where('is_active', true)->count();

        $totalOveragePending = OverageCharge::where('status', 'pending')->sum('amount');

        $moduleUsage = Module::withCount(['tenants'])->get()->map(fn ($m) => [
            'name' => $m->name,
            'slug' => $m->slug,
            'tenant_count' => $m->tenants_count,
        ]);

        $previousMonthTenants = Tenant::where('created_at', '<', now()->subMonth())->count();
        $previousMonthCancellations = Subscription::where('status', 'cancelled')
            ->where('created_at', '<', now()->subMonth())
            ->count();

        $monthlyChurn = $previousMonthTenants > 0
            ? round(($cancelledSubscriptions / $previousMonthTenants) * 100, 2)
            : 0;

        $previousQuarterTenants = Tenant::where('created_at', '<', now()->subMonths(3))->count();
        $quarterlyChurn = $previousQuarterTenants > 0
            ? round(($cancelledSubscriptions / $previousQuarterTenants) * 100, 2)
            : 0;

        $averageRevenuePerTenant = $activeTenants > 0
            ? round(($totalArr + (float) $mrr * 12) / $activeTenants, 2)
            : 0;

        $recentTenants = Tenant::latest()->take(5)->get()->map(fn ($t) => [
            'id' => $t->id,
            'company_name' => $t->company_name,
            'created_at' => $t->created_at,
        ]);

        $recentSubscriptions = Subscription::with(['tenant', 'plan'])
            ->latest()
            ->take(5)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'tenant_name' => $s->tenant?->company_name,
                'plan_name' => $s->plan?->name,
                'status' => $s->status->value,
                'created_at' => $s->created_at,
            ]);

        return $this->api->success(
            message: 'Dashboard data retrieved successfully',
            data: [
                'stats' => [
                    'tenants' => [
                        'total' => $totalTenants,
                        'active' => $activeTenants,
                        'trial' => $trialTenants,
                        'suspended' => $suspendedTenants,
                        'expired' => $expiredTenants,
                    ],
                    'users' => [
                        'total' => $totalUsers,
                        'active' => $activeUsers,
                    ],
                    'plans' => [
                        'total' => $totalPlans,
                        'active' => $activePlans,
                    ],
                    'features' => [
                        'total' => $totalFeatures,
                        'active' => $activeFeatures,
                    ],
                    'subscriptions' => [
                        'total' => $totalSubscriptions,
                        'active' => $activeSubscriptions,
                        'new' => $newSubscriptions,
                        'expiring_soon' => $expiringSoon,
                        'cancelled' => $cancelledSubscriptions,
                    ],
                    'revenue' => [
                        'mrr' => (float) $mrr,
                        'arr' => $totalArr,
                        'average_revenue_per_tenant' => $averageRevenuePerTenant,
                        'revenue_growth' => 0,
                    ],
                    'churn' => [
                        'monthly_churn' => $monthlyChurn,
                        'quarterly_churn' => $quarterlyChurn,
                        'annual_churn' => 0,
                    ],
                    'billing' => [
                        'pending_overage' => (float) $totalOveragePending,
                    ],
                ],
                'module_usage' => $moduleUsage,
                'recent_activity' => [
                    'new_tenants' => $recentTenants,
                    'new_subscriptions' => $recentSubscriptions,
                ],
            ],
        );
    }
}
