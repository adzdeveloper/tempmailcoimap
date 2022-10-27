<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

use App\Helpers\cPanelApi;


class CreateTempMailOnCpanel implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected $data=[];
    public function __construct($temp)
    {
        $this->data=$temp;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {


        \Log::channel('scheduler')->info('server connect error'.json_encode($this->data));

        $username='proapp';
        $password='22khkjNBKJHGyt4e646';
        $url='proappdevelopment.net';
        
        $tt='tmail@tempmails.net';

        $tempmail=$this->data['email'];
        $api=new cPanelApi($url,$username,$password);
        $res= $api->createEmail($this->data['email'], $url, "25"); //here $url is password basically
        $api->addForwarder($this->data['email'],$tt,$this->data['domain']);
        
        \Log::channel('scheduler')->info($this->data['email'].'created successfully');
    }
}
