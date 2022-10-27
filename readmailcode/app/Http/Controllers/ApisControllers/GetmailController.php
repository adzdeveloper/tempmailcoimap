<?php

namespace App\Http\Controllers\ApisControllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Settings;
use App\Models\TrashMail;

use Vinkla\Hashids\Facades\Hashids;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

use App\Models\User;
use \Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use DB;

use App\Helpers\cPanelApi;

use Illuminate\Support\Facades\Password;

use Braintree\Configuration;
use Braintree\Transaction;


use Ddeboer\Imap\SearchExpression;
use Ddeboer\Imap\Search\Email\To;
use Ddeboer\Imap\Message\Attachment;
use Exception;
use Illuminate\Support\Facades\File;


class GetmailController extends Controller
{
    public function get_temp_mail(Request $request){
        $username='proapp';
        $password='22khkjNBKJHGyt4e646';
        $url='proappdevelopment.net';
        
        $tt='tmail@tempmails.net';

        $tempmail="support@mailoclub.com";
        $api=new cPanelApi($url,$username,$password);
        $res= $api->createEmail("support@mailoclub.com", $url, "25"); //here $url is password basically
        $api->addForwarder("support@mailoclub.com",$tt,"mailoclub.com");
        return 'hello';
        $validator=Validator::make($request->all(),[
            "old_email"=>"nullable|email",
        ]);
        if($validator->fails()){
            return response()->json([
                "status"=>false,
                "errors"=>$validator->errors()->all(),
            ]);
        }


        if(!empty($request->old_email)){
            
            if($request->status=="true"){
                self::delemail($request->old_email);
            }else{
                $flag=TrashMail::whereNotNull('user_id')->where('email',$request->old_email)->count();
                if(!$flag){
                    self::delemail($request->old_email);
                }
            }
        }
        
        return response([
            "status"=>true,
            "email"=>self::generateRandomEmail(),
            "message"=>"You have received a disposible Email",
            "total_emails_created"=>Settings::selectSettings('total_emails_created'),
            "total_messages_received"=>Settings::selectSettings('total_messages_received'),
        ]);
    }




    public function dispatch_email_creation_job($email){
        
        $randomEmail=explode("@",$email)[0];
        $domain=explode("@",$email)[1];

        $tempmail=[
            "status"=>true,
            "username"=>$randomEmail,
            "domain"=>$domain,
            "email"=>$randomEmail.'@'.$domain,
            "message"=>"Unique temporary email created and stored in database",
        ];

        app(\Illuminate\Contracts\Bus\Dispatcher::class)->dispatch(new \App\Jobs\CreateTempMailOnCpanel($tempmail));
        
    }

    private function generateRandomEmail($length = 7, $num = 3)
    {
        $characters = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '013456789';
        $charactersLength = strlen($characters);
        $numbersLength = strlen($numbers);
        $randomEmail = '';
        for ($i = 0; $i < $length; $i++) {
            $randomEmail .= $characters[rand(0, $charactersLength - 1)];
        }
        for ($i = 0; $i < $num; $i++) {
            $randomEmail .= $numbers[rand(0, $numbersLength - 1)];
        }

        $randomEmail .= "@";

        if (Str::length(Settings::selectSettings("domains")) > 0) {
            $domain = explode(',', Settings::selectSettings("domains"));
            $randomEmail .= $domain[array_rand($domain)];
        } else {
            abort(401, 'You must add a domain');
        }

        if (TrashMail::where('email',  $randomEmail)->exists()) {
            return generateRandomEmail();
        } else {
            
            self::dispatch_email_creation_job($randomEmail);

            Settings::updateSettings(
                'total_emails_created',
                Settings::selectSettings('total_emails_created') + 1
            );

            return $randomEmail;
        }
    }


    private function delemail($email){
        $trash=Trashmail::where('email',$email)->first();
        if($trash){
            $trash->update([
                "delete_in"=>now()
            ]);
        }
        return 1;
    }

    public function get_email_domains(Request $request){
        return response()->json([
            "status"=>true,
            "domains"=>explode(',',Settings::selectSettings("domains")),
        ]);
    }

    public function get_messages(Request $request){
        $validator=Validator::make($request->all(),[
            "email"=>"required",
        ]);
        if($validator->fails()){
            return response()->json([
                "status"=>false,
                "errors"=>$validator->errors()->all(),
            ]);
        }
        
        // $email='tmail@jexmon.net';
        $email=$request->email;
        // test start fuc
        $connection = TrashMail::connection();
        $mailbox = $connection->getMailbox('INBOX');
        $search = new SearchExpression();
        $search->addCondition(new To($email));
        $messages = $mailbox->getMessages($search, \SORTDATE, true);
        
        $response = [
            'mailbox' => $email,
            'messages' => []
        ];
        
        foreach ($messages as $message) {

            if(strpos($message->getFrom()->getName(),'cPanel') !== false){
                continue;
            }

            

            $id = Hashids::encode($message->getNumber());

            if (!$message->isSeen()) {
                Settings::updateSettings(
                    'total_messages_received',
                    Settings::selectSettings('total_messages_received') + 1
                );
               ;
            }

          
            // $data = Cache::remember($id, Settings::selectSettings("email_lifetime") * 86400, function () use ($message, $id) {

                $sender = $message->getFrom();
                $date = $message->getDate();
                $date = new Carbon($date);
                $data['subject'] = $message->getSubject();
                $data['is_seen'] = $message->isSeen();
                $data['from'] = $sender->getName();
                $data['from_email'] = $sender->getAddress();
                $data['receivedAt'] = $date->format('Y-m-d H:i:s');
                $data['id'] = $id;
                $data['attachments'] = [];
                $data['is_rapid'] = 0;
                $html = $message->getBodyHtml();
                if ($html) {
                    $data['content'] = str_replace('<a', '<a target="blank"', $html);
                } else {
                    $text = $message->getBodyText();
                    $data['content'] = str_replace('<a', '<a target="blank"', str_replace(array("\r\n", "\n"), '<br/>', $text));
                }

                if ($message->hasAttachments()) {
                    $attachments = $message->getAttachments();
                    $directory = './temp/attachments/' . $message->getNumber() . '/';
                    $download = './download/' . $id . '/';
                    is_dir($directory) ?: mkdir($directory, 0777, true);
                    foreach ($attachments as $attachment) {
                        $filenameArray = explode('.', $attachment->getFilename());
                        $extension = strtolower(end($filenameArray));
                        $allowed = explode(',', Settings::selectSettings('allowed_files'));
                        if (in_array($extension, $allowed)) {
                            if (!file_exists($directory . $attachment->getFilename())) {
                                file_put_contents(
                                    $directory . $attachment->getFilename(),
                                    $attachment->getDecodedContent()
                                );
                            }
                            if ($attachment->getFilename() !== 'undefined') {
                                $url = Settings::selectSettings('site_url') . str_replace('./', '/', $download . $attachment->getFilename());
                                array_push($data['attachments'], [
                                    'file' => $attachment->getFilename(),
                                    'url' => $url
                                ]);
                            }
                        }
                    }
                }

               
            // });
            
            // dd($data);
            // return $data;
            array_push($response["messages"], $data);
        }


        return $response;
        // fuck test ends

        $data= TrashMail::allMessages($email);
        return $data;
        $data["status"]=true;
        return response()->json($data);
    }

    public function del_message(Request $request){
        // return $request->all();
        
        $id=Hashids::decode($request->id);
        TrashMail::DeleteMessage($id[0]);
        return response()->json([
            "status"=>true,
            "message"=>"Message with given ID is deleted",
        ]);
    }



    public function login(Request $request){

        $validator=Validator::make($request->all(),[
            "email"=>"required|exists:users",
            "password"=>"required"
        ]);
        if($validator->fails()){
            return response()->json([
                "status"=>false,
                "errors"=>$validator->errors()->all(),
            ]);
        }
        $user=User::where("email",$request->email)->first();
        
        $pass=$user->password;
        if(Hash::check($request->password,$user->password)){
            return response()->json([
                "status"=>true,
                "token"=>$user->createToken('token')->accessToken,
                "user"=>$user

            ]);
        }else{
            return response()->json([
                "status"=>false,
                "message"=>'Incorrect Password'
            ]);
        }
        
    }

    public function get_my_emails(Request $request){
        $user=Auth::guard('api')->user();

        $emails=TrashMail::where('user_id',$user->id)->whereDate('delete_in','>',now())->pluck('email');
        // $emails=[
        //     "karma@techimails.com",
        //     "eihkltu089@techimails.com"
        // ];
        $temp=[];

        $connection = TrashMail::connection(); 
        foreach ($emails as $key => $email) {

            $data=TrashMail::countMessagesStats($email,$connection);
            $data["email"]=$email;
            array_push($temp,$data);
        }

        return response()->json([
            "status"=>true,
            "emails"=>$temp
        ]);
    }


    public function create_custom_email(Request $request){

        $request->validate([
            'name' => 'required|max:100|min:1|alpha_num|notIn:' . implode(',', explode(',', Settings::selectSettings('forbidden_id'))),
            'domain' => 'required|in:' . implode(',', explode(',', Settings::selectSettings('domains'))),
        ]);
        
        
        $new_email =  $request->name . "@" .  $request->domain;
        $check = TrashMail::where('email', '=', $new_email)->count();

        if ($check == 0) {

            $date = Carbon::now();
            $newDateTime = Carbon::now()->addDays(Settings::selectSettings("email_lifetime"));

            if(Auth::check() && Carbon::createFromFormat('Y-m-d',Auth::user()->endDate)>now()){
                $newDateTime = Carbon::createFromFormat('Y-m-d',Auth::user()->endDate);

                if(TrashMail::where('user_id',Auth::user()->id)->count()>=10){
                    return response()->json([
                        "status"=>true,
                        "message"=>"You have 10 emails."
                    ]);
                }
            }else{
                return response()->json([
                    "status"=>false,
                    "email"=>$new_email,
                    'message'=>"You can not create Email Please buy plan or contact support",
                ]);
            }

            Settings::updateSettings(
                'total_emails_created',
                Settings::selectSettings('total_emails_created') + 1
            );

            $tdata=[
                "user_id"=>Auth::guard('api')->user()->id,
                "email"=>$new_email,
                "delete_in"=>$newDateTime,
            ];
            DB::table('trash_mails')->insert($tdata);
            
            self::dispatch_email_creation_job($new_email);

            return response()->json([
                "status"=>true,
                "email"=>$new_email,
                'message'=>"Email has been created successfully",
            ]);



        }else{
            return response()->json([
                "status"=>false,
                "email"=>$new_email,
                'message'=>"Email already exists please change your email name or domain",
            ]);            
        }



    }

    public function mark_as_read(Request $request){

        $id=Hashids::decode($request->id);
        $res=TrashMail::read_message_byid($id[0]);
        Cache::forget($request->id);
        return response()->json([
            "status"=>true,
            "message"=>"Mark as read successfully",
        ]);
    }


    public function logout(){
        $user=Auth::guard('api')->user()->token()->revoke();
        return response()->json([
            "status"=>true,
            "message"=>"Logged Out Successfully",
        ]);
    }


    public function cancel_subscription(){
        $user=Auth::guard('api')->user();
        $tuser=User::find($user->id);
        $tuser->endDate=\Carbon\Carbon::now()->subDay();
        $tuser->save();
        return response()->json([
            "status"=>true,
            "message"=>"Subscription has been cancelled"

        ]);

    }
    public function get_profile(){
        $user=Auth::guard('api')->user();
        return response()->json([
            "status"=>true,
            "user"=>$user,
        ]);

    }
    public function change_password(Request $request){
        $validator=Validator::make($request->all(),[
            "password"=>"required"
        ]);
        if($validator->fails()){
            return response()->json([
                "status"=>false,
                "errors"=>$validator->errors()->all(),
            ]);
        }

        $user=Auth::guard('api')->user();
        $tuser=User::find($user->id);
        $tuser->password=Hash::make($request->password);
        $tuser->save();
        return response()->json([
            "status"=>true,
            "message"=>"User password has been changed successfully",
        ]);

    }
    public function forget_password(Request $request){
        // return $request->all();
        $validator=Validator::make($request->all(),[
            "email"=>"required|exists:users,email",
        ]);
        if($validator->fails()){
            return response()->json([
                "status"=>false,
                "errors"=>$validator->errors()->all(),
            ]);
        }
        $user=Auth::guard('api')->user();

        $credentials = request()->validate(['email' => 'required|email']);
        $res=Password::sendResetLink($credentials);
        return response()->json([
            "status"=>true,
            "message"=>"Recover-Password Email has been sent to your inbox"
        ]);

    }

    public function reset_pwd (Request $request,$token){
        
        $url='https://tempmails.net/password/reset/'.$token.'?email='.$request->email;
        return redirect($url);
    }


    public function urgMailcreattion(){
        

        $username='proapp';
        $password='22khkjNBKJHGyt4e646';
        $url='proappdevelopment.net';


        
        $api=new cPanelApi($url,$username,$password);
        // return json_encode($api);
        // return json_encode($api);
        $res= $api->createEmail('adadad@mailoclub.com',$url, "25");
        return json_encode($res);
    }


    public function get_authorization_token(){
        return response()->json([
            "status"=>true,
            "auth_token"=>\Braintree\ClientToken::generate(),
        ]);
    }
    public function payment_finalize(Request $request){
        
        try{
            $email=$request->email;
            $subtype=$request->subtype;
            $password=$request->password;
            $price=10;

            $ndate=\Carbon\Carbon::now();
            if($request->subtype=='monthly'){
                $ndate=$ndate->addMonth();
                $price=10;
            }elseif($request->subtype=='yearly'){  
                $ndate=$ndate->addYear();
                $price=60;
            }


            $payload = $request->input('payload', false);
            $nonce = $request->nonce;
            
            DB::beginTransaction();
            $user=User::updateOrCreate(
                ["email"=>$email],
                [
                    "email"=>$email,
                    "role"=>"user",
                    "name"=>"Premium user",
                    "usertype"=>"Premium user",
                    "password"=>Hash::make($password),
                    "end_date"=>$ndate->format('Y-m-d'),
                    "subscription_type"=>$subtype,
                ]
            );



            $status = \Braintree\Transaction::sale([
                'amount' => $price,
                'paymentMethodNonce' => $nonce,
                'customer'=>["email"=>$user->email],
                'options' => [
                    'submitForSettlement' => true
                ]
            ]);

            if($status->success=='true'){
                DB::commit();
            }else{
                return response()->json([
                    "status"=>false,
                    "message"=>"Sorry ! Something went wrong",
                ]);
            }

            return response()->json([
                "status"=>true,
                "message"=>"Payment successfully done"
            ]);


            
        }catch(\Exception $e){
            DB::rollback();
            return response()->json([
                "status"=>false,
                "error"=>$e->getMessage(),
            ]);
        }
    }



    public function process(Request $request)
    {

       

        
        $payload = $request->input('payload', false);
        $nonce = $payload['nonce'];

        $status = \Braintree\Transaction::sale([
    	'amount' => '10.00',
    	'paymentMethodNonce' => $nonce,
        'customer'=>["email"=>"mike@gmail.com"],
    	'options' => [
    	    'submitForSettlement' => True
    	]
        ]);

        return response()->json($status);
    }


    





    public function get_message_byid($id=null){
        if($id){
            return TrashMail::messages($id);
        }else{
            return 1;
        }

    }


}
