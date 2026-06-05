<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Helpers\AppSupport;
use Illuminate\Support\Collection;
use Illuminate\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;

class ClientController extends Controller
{
    public function view(Request $request)
    {
        $company = AppSupport::getCompanyOfAuthUser();
        
        if (!$request->has('phone')) {
            return redirect('dashboard')->with('error', 'Invalid Request');
        }

        $phone = $request->phone;
        $client = DB::table('users')->where('phone', $phone)->first();

        if (!$client) {
            return redirect('dashboard')->with('error', 'Client not found');
        }

        $mergedDocuments = collect();

        try {
            $documentViews = DB::table('document_views')
                ->leftJoin('docs', 'docs.id', '=', 'document_views.document_id')
                ->where('document_views.user_id', $client->id)
                ->when($company, function ($query) use ($company) {
                    return $query->where('document_views.sender_id', $company->id);
                })
                ->whereIn('document_views.status', ['accepted', 'survey'])
                ->select('docs.text as name', 'document_views.status', 'document_views.doc_hash as path', 'document_views.created_at', 'document_views.document_id', 'docs.archived_at')
                ->get();

            $documentsA = $documentViews->map(function ($doc) {
                return (object) [
                    'name' => $doc->name ?? 'Requested Document',
                    'type' => ($doc->status == "survey") ? "Survey" : "Requested",
                    'path' => "document/" . $doc->path,
                    'created_at' => $doc->created_at,
                    'docs_table_id' => $doc->document_id,
                    'archived_at' => $doc->archived_at,
                ];
            });
            $mergedDocuments = $mergedDocuments->concat($documentsA);
        } catch (\Exception $e) {}

        try {
            $sentDocuments = DB::table('docs')
                ->where('user_id', $client->id)
                ->when($company, function ($query) use ($company) {
                    return $query->where('sent_by', $company->id);
                })
                ->select('id', 'text as name', 'created_at', 'doc_token as path')
                ->get();

            $documentsB = $sentDocuments->map(function ($doc) {
                return (object) [
                    'docs_table_id' => $doc->id,
                    'name' => $doc->name ?? 'Sent Document',
                    'type' => "Sent",
                    'path' => "sent_document/" . $doc->path,
                    'created_at' => $doc->created_at,
                ];
            });
            $mergedDocuments = $mergedDocuments->concat($documentsB);
        } catch (\Exception $e) {}

        try {
            $signedDocuments = DB::table('signs')->where('user_id', $client->id)->get();
            $documentsC = $signedDocuments->map(function ($doc) {
                return (object) [
                    'name' => $doc->name ?? 'Signed Document',
                    'type' => "Signed",
                    'path' => "sign/" . $doc->hash,
                    'created_at' => $doc->created_at,
                    'docs_table_id' => $doc->hash,
                ];
            });
            $mergedDocuments = $mergedDocuments->concat($documentsC);
        } catch (\Exception $e) {}

        try {
            $sharedDocuments = DB::table('share_docs')
                ->leftJoin('docs', 'docs.id', '=', 'share_docs.document_id')
                ->where('share_docs.user_id', $client->id)
                ->when($company, function ($query) use ($company) {
                    return $query->where('share_docs.business_id', $company->id);
                })
                ->select('docs.text as name', 'share_docs.created_at', 'share_docs.id as path', 'share_docs.document_id')
                ->get();

            $documentsD = $sharedDocuments->map(function ($doc) {
                return (object) [
                    'name' => $doc->name ?? 'Shared Document',
                    'type' => "Shared",
                    'path' => "share_document/" . $doc->path,
                    'created_at' => $doc->created_at,
                    'docs_table_id' => $doc->document_id,
                ];
            });
            $mergedDocuments = $mergedDocuments->concat($documentsD);
        } catch (\Exception $e) {}

        $mergedDocuments = $mergedDocuments->unique('docs_table_id');
        $page = (!empty($_GET['page'])) ? $_GET['page'] : 1;
        $mergedDocuments = $this->paginate_array($mergedDocuments, 8, $page, [
            'path' => request()->url() . '?phone=' . $phone
        ]);

        $surveys = new LengthAwarePaginator([], 0, 8);
        try {
            $query = DB::table('surveys')
                ->join('questionsets', 'questionsets.id', '=', 'surveys.formId')
                ->join('users', 'users.id', '=', 'surveys.userId')
                ->where('surveys.userId', $client->id)
                ->select('surveys.*', 'questionsets.title', 'questionsets.id as questionSetId', 'questionsets.type as questionSetType', 'users.first_name', 'users.last_name');

            if ($company) {
                $query->where('questionsets.businessId', $company->id);
            }

            if ($request->has('question_set') && !empty($request->question_set)) {
                $query->where('questionsets.title', 'LIKE', '%' . $request->question_set . '%');
            }

            if ($request->has('application_status') && !empty($request->application_status)) {
                $query->where('surveys.application_status', $request->application_status);
            }

            $surveys = $query->orderBy('surveys.created_at', 'desc')->paginate();
        } catch (\Exception $e) {}

        $extraDetailsFields = [];
        try {
            $extraDetails = DB::table('client_extra_details')->where('user_id', $client->id)->first();
            $extraDetailsFields = $extraDetails ? json_decode($extraDetails->additional_fields, true) : [];
        } catch (\Exception $e) {}

        return view('client.info', [
            'phone' => $client->phone,
            'clientName' => ($client->first_name ?? '') . ' ' . ($client->last_name ?? ''),
            'documentViewsDocs' => $mergedDocuments,
            'surveys' => $surveys,
            'extraDetailsFields' => $extraDetailsFields,
        ]);
    }

    public function paginate_array($items, $perPage = 8, $page = null, $options = [])
    {
        $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
        $items = $items instanceof Collection ? $items : Collection::make($items);
        return new LengthAwarePaginator($items->forPage($page, $perPage), $items->count(), $perPage, $page, $options);
    }
}