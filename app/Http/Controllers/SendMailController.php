<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Mail;
use App\Mail\Testmail;

class SendMailController extends Controller
{
    $subject = 'Test Subject';
    $body = 'Test Message';

    Mail::to('nanfackhans900@gmail.com')->send(new Testmail($subject,$body));
}
