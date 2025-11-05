<?php

use Illuminate\Support\Facades\Schedule;
use App\Jobs\NotifyUsersByExpiryOffsetJob;

$tz = 'America/Sao_Paulo';

// -3 dias: lembrete
Schedule::job(new NotifyUsersByExpiryOffsetJob(-3, 'upcoming'))
    ->dailyAt('06:00')->timezone($tz)->onOneServer()->withoutOverlapping();

// 0 dia: criar fatura + enviar
Schedule::job(new NotifyUsersByExpiryOffsetJob(0, 'invoice'))
    ->dailyAt('06:05')->timezone($tz)->onOneServer()->withoutOverlapping();

// +1 dia: vencido
Schedule::job(new NotifyUsersByExpiryOffsetJob(1, 'overdue1'))
    ->dailyAt('06:10')->timezone($tz)->onOneServer()->withoutOverlapping();

// +3 dias: “aconteceu algo?”
Schedule::job(new NotifyUsersByExpiryOffsetJob(3, 'winback3'))
    ->dailyAt('06:15')->timezone($tz)->onOneServer()->withoutOverlapping();

// +5 dias: mensagem final
Schedule::job(new NotifyUsersByExpiryOffsetJob(5, 'winback5'))
    ->dailyAt('06:20')->timezone($tz)->onOneServer()->withoutOverlapping();
