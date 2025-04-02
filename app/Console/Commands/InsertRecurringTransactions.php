<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;

class InsertRecurringTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:insert-recurring-transactions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command will insert a weekly recurring transaction at the transactions table.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
        $transactions = Transaction::where('status','1')->where('is_recurring','Y')->where('recurring_period','WEEKLY')->get();
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
            
        }
        $this->info("recurring transaction added");
    }
}
