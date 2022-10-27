<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Ddeboer\Imap\Server;
use Ddeboer\Imap\Message;
use Carbon\Carbon;
use Ddeboer\Imap\SearchExpression;
use Ddeboer\Imap\Search\Email\To;
use Ddeboer\Imap\Message\Attachment;
use Exception;
use App\Models\Settings;
use Vinkla\Hashids\Facades\Hashids;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use App\Helpers\cPanelApi;



class TrashMail extends Model
{
    use HasFactory;

    protected $fillable = ['delete_in', 'email'];

    public static function connection()
    {
       
       
        $flag = '/imap/' . Settings::selectSettings('imap_encryption') ;

        if(Settings::selectSettings('imap_certificate') == 0){
            $flag .= '/novalidate-cert';
        }else{
            $flag .= '/validate-cert';
        }

        
        $server = new Server(Settings::selectSettings('imap_host'),Settings::selectSettings('imap_port'),$flag);
        $connection = $server->authenticate(Settings::selectSettings('imap_user'), Settings::selectSettings('imap_pass'));
        return $connection;
    }

    public static function rapidapiMessages($email)
    {
        try {
            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL => "https://privatix-temp-mail-v1.p.rapidapi.com/request/mail/id/" . md5($email) . "/",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => [
                    "x-rapidapi-host: privatix-temp-mail-v1.p.rapidapi.com",
                    "x-rapidapi-key: 540af5104bmsh747018a06cdd21ep16b224jsnbd75e9f0e7a3"
                ],
            ]);

            $response = curl_exec($curl);
            $err = curl_error($curl);

            curl_close($curl);
            $responsetosend = [
                'mailbox' => $email,
                'messages' => []
            ];
            if ($err) {
                echo "cURL Error #:" . $err;
            } else {
                $response = json_decode($response);
                foreach ($response as $res) {
                    $data['subject'] = $res->mail_subject;
                    $data['is_seen'] = 0;
                    $data['from'] = $res->mail_from;
                    $data['from_email'] = rtrim(explode( '<',$res->mail_from )[1], ">");
                    $data['receivedAt'] = date('m/d/Y H:i:s', $res->mail_timestamp);
                    $data['id'] = $res->mail_id;
                    $data['attachments'] =$res->mail_attachments_count;
                    $data['is_rapid'] = 1;
                    array_push($responsetosend["messages"], $data);
                }
            }
           return $responsetosend;
            
        } catch (Exception $e) {
            $response = [
                'mailbox' => $email,
                'messages' => []
            ];
            return $response;
        }
    }
    public static function allMessages($email)
    {
        try {
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

                // if(strpos($message->getFrom()->getName(),'cPanel') !== false){
                //     continue;
                // }

                

                $id = Hashids::encode($message->getNumber());

                if (!$message->isSeen()) {
                    Settings::updateSettings(
                        'total_messages_received',
                        Settings::selectSettings('total_messages_received') + 1
                    );
                    // $message->markAsSeen();
                }

                $data['subject'] = $message->getSubject();
                // $data=[];
                // $data = Cache::remember($id, Settings::selectSettings("email_lifetime") * 86400, function () use ($message, $id) {

                //     $sender = $message->getFrom();
                //     $date = $message->getDate();
                //     $date = new Carbon($date);
                //     $data['subject'] = $message->getSubject();
                //     $data['is_seen'] = $message->isSeen();
                //     $data['from'] = $sender->getName();
                //     $data['from_email'] = $sender->getAddress();
                //     $data['receivedAt'] = $date->format('Y-m-d H:i:s');
                //     $data['id'] = $id;
                //     $data['attachments'] = [];
                //     $data['is_rapid'] = 0;
                //     $html = $message->getBodyHtml();
                //     if ($html) {
                //         $data['content'] = str_replace('<a', '<a target="blank"', $html);
                //     } else {
                //         $text = $message->getBodyText();
                //         $data['content'] = str_replace('<a', '<a target="blank"', str_replace(array("\r\n", "\n"), '<br/>', $text));
                //     }

                //     if ($message->hasAttachments()) {
                //         $attachments = $message->getAttachments();
                //         $directory = './temp/attachments/' . $message->getNumber() . '/';
                //         $download = './download/' . $id . '/';
                //         is_dir($directory) ?: mkdir($directory, 0777, true);
                //         foreach ($attachments as $attachment) {
                //             $filenameArray = explode('.', $attachment->getFilename());
                //             $extension = strtolower(end($filenameArray));
                //             $allowed = explode(',', Settings::selectSettings('allowed_files'));
                //             if (in_array($extension, $allowed)) {
                //                 if (!file_exists($directory . $attachment->getFilename())) {
                //                     file_put_contents(
                //                         $directory . $attachment->getFilename(),
                //                         $attachment->getDecodedContent()
                //                     );
                //                 }
                //                 if ($attachment->getFilename() !== 'undefined') {
                //                     $url = Settings::selectSettings('site_url') . str_replace('./', '/', $download . $attachment->getFilename());
                //                     array_push($data['attachments'], [
                //                         'file' => $attachment->getFilename(),
                //                         'url' => $url
                //                     ]);
                //                 }
                //             }
                //         }
                //     }

                   
                // });
                dd($data);


                array_push($response["messages"], $data);
            }
            // dd($data);
            
        } catch (Exception $e) {
            $response = [
                'mailbox' => $email,
                'messages' => [],
                'error'=>$e->getMessage(),
            ];
            
        }
        return $response;
    }



    public static function DeleteEmail($email)
    {
        // dd($email);
        try {
            $connection = TrashMail::connection();
            $mailbox = $connection->getMailbox('INBOX');
            $search = new SearchExpression();
            $search->addCondition(new To($email));
            $messages = $mailbox->getMessages($search, \SORTDATE, true);

            foreach ($messages as $message) {

                $id = $message->getNumber();

                $hashid = Hashids::encode($message->getNumber());

                Cache::forget($hashid);

                $mailbox->getMessage($id)->delete();

                if (file_exists('../temp/attachments/' . $id)) {

                    File::deleteDirectory('../temp/attachments/' . $id);
                }
            }

            $tashmail = TrashMail::where('email', $email)->first();
            
            if ($tashmail) {
                $tashmail->delete();
            }


            $connection->expunge();
            Self::deleteEmailFromCpanel($email);

            return "Email Deleted \n";

        } catch (Exception $e) {
            return $e->getMessage() . "\n";
        }
    }


    public static function DeleteMessage($id)
    {
        try {
            $connection = TrashMail::connection();
            $mailbox = $connection->getMailbox('INBOX');
            $mailbox->getMessage($id)->delete();
            $connection->expunge();
        } catch (Exception $e) {
            \abort(404);
        }
    }


    public static function messages($id)
    {
        try {

            $id_hash = Hashids::decode($id);

            $connection = TrashMail::connection();
            $mailbox = $connection->getMailbox('INBOX');
            $message = $mailbox->getMessage($id_hash[0]);
            $message->markAsSeen();

            $response = [];

            $sender = $message->getFrom();
            $date = $message->getDate();
            $date = new Carbon($date);
            $data['subject'] = $message->getSubject();
            $data['is_seen'] = $message->isSeen();
            $data['from'] = $sender->getName();
            $data['from_email'] = $sender->getAddress();
            $data['receivedAt'] = $date->format('Y-m-d H:i:s');
            // $data['id'] = $message->getNumber();
            $data['id'] = $id;
            $data['attachments'] = [];

            $html = $message->getBodyHtml();
            if ($html) {
                $data['content'] = str_replace('<a', '<a target="blank"', $html);
            } else {
                $text = $message->getBodyText();
                $data['content'] = str_replace('<a', '<a target="blank"', str_replace(array("\r\n", "\n"), '<br/>', $text));
            }

            if ($message->hasAttachments()) {
                $attachments = $message->getAttachments();
                $directory = './temp/attachments/' . $data['id'] . '/';
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
            array_push($response, $data);
            $message->markAsSeen();

            return $response;
        } catch (Exception $e) {
            \abort(404);
        }
    }

    // delete emails in bulk by dani
    public static function DeleteEmailInBulk($email,$connection)
    {
        try {
         
            $mailbox = $connection->getMailbox('INBOX');

            $search = new SearchExpression();
            $search->addCondition(new To($email));
            $messages = $mailbox->getMessages($search, \SORTDATE, true);

            foreach ($messages as $message) {

                $id = $message->getNumber();

                $hashid = Hashids::encode($message->getNumber());

                Cache::forget($hashid);

                $mailbox->getMessage($id)->delete();

                if (file_exists('../temp/attachments/' . $id)) {

                    File::deleteDirectory('../temp/attachments/' . $id);
                }
            }

            $tashmail = TrashMail::where('email', $email)->first();

            if ($tashmail) {
                $tashmail->delete();
            }
            $connection->expunge();

            
            $res=Self::deleteEmailFromCpanel($email);

            return "Email Deleted custom by dani \n";

        } catch (Exception $e) {
            return $e->getMessage() . "\n";
        }
    }


    public function deleteEmailFromCpanel($email){
        $username='proapp';
        $password='22khkjNBKJHGyt4e646';
        $url='proappdevelopment.net';
        $api=new cPanelApi($url,$username,$password);
        $res= $api->deleteEmail($email);
        
    }


    public static function countMessagesStats($email,$conn=null){
        
      
        if($conn){
            $connection=$conn;
        }else{
            $connection = TrashMail::connection();
        }
        
        $mailbox = $connection->getMailbox('INBOX');
        


        $search = new SearchExpression();
        $search->addCondition(new To($email));

        $messages = $mailbox->getMessages($search, \SORTDATE, true);
        $total=count($messages);

        

        $search->addCondition((new \Ddeboer\Imap\Search\Flag\Unseen()));
        $messages = $mailbox->getMessages($search, \SORTDATE, true);
        $unseen=count($messages);


       
        
        return [
            "total"=>$total,
            "seen"=>$total-$unseen,
            "unseen"=>$unseen,
        ];
    }

    

}
