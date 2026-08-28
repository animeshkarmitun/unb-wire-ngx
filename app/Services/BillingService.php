<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class BillingService
{
    public function mrr(?string $period = null): float
    {
        $q = DB::table('client_packages')
            ->join('packages','packages.id','=','client_packages.package_id')
            ->where('client_packages.status','active')
            ->where('packages.status','active')
            ->where(function($w){ $w->whereNull('client_packages.ends_at')->orWhere('client_packages.ends_at','>', now()); });
        return (float) $q->sum('packages.price_monthly');
    }

    public function generateInvoices(string $periodStart, string $periodEnd): int
    {
        $start = \Carbon\Carbon::parse($periodStart)->startOfMonth()->toDateString();
        $end = \Carbon\Carbon::parse($periodEnd)->startOfMonth()->endOfMonth()->toDateString();
        $clients = DB::table('client_packages')
            ->join('packages','packages.id','=','client_packages.package_id')
            ->where('client_packages.status','active')
            ->where('packages.status','active')
            ->select('client_packages.client_id', DB::raw('SUM(packages.price_monthly) as subtotal'))
            ->groupBy('client_packages.client_id')
            ->get();

        $count = 0;
        foreach($clients as $row){
            if(Invoice::where('client_id',$row->client_id)->whereDate('period_start',$start)->exists()) continue;
            $inv = Invoice::create([
                'client_id'=>$row->client_id,
                'period_start'=>$start,
                'period_end'=>$end,
                'subtotal'=>$row->subtotal,
                'total'=>$row->subtotal,
                'status'=>'issued',
                'issued_at'=>now(),
                'due_at'=>now()->addDays(14),
            ]);
            $pkgs = DB::table('client_packages')->join('packages','packages.id','=','client_packages.package_id')->where('client_packages.client_id',$row->client_id)->where('client_packages.status','active')->select('packages.id','packages.name','packages.price_monthly')->get();
            foreach($pkgs as $p){
                $inv->lines()->create(['package_id'=>$p->id,'description'=>$p->name,'qty'=>1,'unit_price'=>$p->price_monthly,'amount'=>$p->price_monthly]);
            }
            $count++;
        }
        return $count;
    }

    public function markPaid(Invoice $inv): void
    {
        $inv->update(['status'=>'paid','paid_at'=>now()]);
    }
}
