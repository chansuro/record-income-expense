<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;
use App\Models\Notification;

class InsertMonthlyRecurringTransaction extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:insert-monthly-recurring-transaction';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command will insert a monthly recurring transaction at the transactions table.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
        $transactions = Transaction::where('status','1')->where('is_recurring','Y')->where('recurring_period','MONTHLY')->get();
        foreach($transactions as $transaction){
            $data = [
                'title'=> $transaction->title,
                'user_id'=>$transaction->user_id,
                'amount'=>$transaction->amount,
                'category_list_id'=> $transaction->category_list_id,
                'type'=>$transaction->type,
                'document'=>$transaction->document,
                'status'=>$transaction->status,
                'paymentmethod'=>$transaction->paymentmethod,
                'transaction_date'=>NOW(),
                'is_recurring'=> 'N',
                'recurring_period'=> null,
                'parent_transaction'=>$transaction->id
            ];
            Transaction::create($data);
            
            $title = "Recurring transaction added";
            $body = "Hi, Your recurring transaction of £".$transaction->amount." added at your transaction list.";

            $input["body"] = $body;
            $input["title"] = $title; 
            $input["user_id"] = $transaction->user_id; 
            $notification = Notification::create($input);
            
        }
        $this->info("recurring transaction added");
    }
}
