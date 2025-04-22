<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ContactUs;

class ContactUsController extends Controller
{
    public function index()
    {
        $messages = ContactUs::orderBy('created_at', 'desc')->paginate(10); // Fetch messages with pagination
        return view('admin.contact_messages', compact('messages'));
    }
}
