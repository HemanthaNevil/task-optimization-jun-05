<?php

namespace App\Http\Controllers;

class ClientController extends Controller
{

    public function view(Request $request)
    {
        $company = AppSupport::getCompanyOfAuthUser();
        if (!$request->has('phone')) {
            return redirect('dashboard')->with('error', 'Invalid Request');;
        }

        $phone = $request->phone;

        $client = User::where('phone', $phone)->first();

        $documentViewsDocs = DocumentViewsModel::where('user_id', $client->id)
            ->where('sender_id', $company->id)
            ->where(function ($query) {
                $query->where('status', '=', 'accepted')
                    ->orWhere('status', '=', 'survey');
            })
            ->with('docsData')
            ->get();


        $sentDocuments = DocsModel::where('user_id', $client->id)
            ->where('sent_by', $company->id)
            ->get();


        $signedDocuments = Sign::where('user_id', $client->id)
            ->where('sender_id', $company->id)
            ->get();

        $sharedDocuments = ShareDocs::where('user_id', $client->id)
            ->where('business_id', $company->id)
            ->with('docsData')
            ->get();

        $verificationDocs = UserVerificationDoc::where('user_id', $client->id)
            ->where('completion_id', '<>', null)
            ->select(['id', 'id_path', 'selfie_path', 'verification_date', 'created_at', 'completion_id', 'user_id', 'is_verified'])
            ->get()
            ->groupBy('completion_id');

        $documentsA = $documentViewsDocs->map(function ($doc) {
            if ($doc->status == "survey") {
                $type = "Survey";
            } else if ($doc->status == "accepted") {
                $type = "Requested";
            }
            return (object)  [
                'name' => @$doc->docsData->text,
                'type' => $type,
                'path' => "document/" . @$doc->doc_hash,
                'created_at' => @$doc->created_at,
                'docs_table_id' => @$doc->docsData->id,
                'archived_at' => @$doc->docsData->archived_at,
            ];
        });

        $documentsB = $sentDocuments->map(function ($doc) {
            return (object) [
                'docs_table_id' => $doc->id,
                'name' => $doc->text,
                'type' => "Sent",
                'path' => "sent_document/" . $doc->doc_token,
                'created_at' => $doc->created_at,
            ];
        });

        $documentsC = $signedDocuments->map(function ($doc) {
            return (object) [
                'name' => $doc->name,
                'type' => "Signed",
                'path' => "sign/" . $doc->hash,
                'created_at' => $doc->created_at,
                'docs_table_id' => $doc->hash,
            ];
        });

        $documentsD = $sharedDocuments->map(function ($doc) {
            return (object)  [
                'name' => $doc->docsData->text,
                'type' => "Shared",
                'path' => "share_document/" . $doc->id,
                'created_at' => $doc->created_at,
                'docs_table_id' => $doc->docsData->id,
            ];
        });

        $documentsE = $verificationDocs->map(function ($verificationDoc) {

            $idDocument = $verificationDoc->where('id_path', '<>', null)->first();
            $selfieDocument = $verificationDoc->where('selfie_path', '<>', null)->first();

            $idPath = $idDocument->id_path ?? null;
            $selfiePath = $selfieDocument->selfie_path ?? null;


            if ($idPath && $selfiePath) {
                $extension = pathinfo($idPath, PATHINFO_EXTENSION);
                $idHash = $extension == 'verime' ? pathinfo(pathinfo($idPath, PATHINFO_FILENAME), PATHINFO_FILENAME) : pathinfo($idPath, PATHINFO_FILENAME);

                $extension = pathinfo($selfiePath, PATHINFO_EXTENSION);
                $imageHash = $extension == 'verime' ? pathinfo(pathinfo($selfiePath, PATHINFO_FILENAME), PATHINFO_FILENAME) : pathinfo($selfiePath, PATHINFO_FILENAME);

                if (!Storage::disk('temp')->exists("$idHash-$imageHash.pdf")) {
                    AppSupport::createPDFWithIDandSelfie($idDocument, $selfieDocument, $idHash, $imageHash, $selfieDocument->is_verified);
                }
            } else {
                return null;
            }
            $record = $verificationDoc->first();
            return (object) [
                'name' => 'Verification Doc_' . $record->verification_date,
                'type' => 'Verification',
                'path' => "verification/$idHash-$imageHash?user_id={$record->user_id}&completion_id={$record->completion_id}&id_hash={$idHash}&image_hash={$imageHash}",
                'is_verified' => (bool) $record->is_verified,
                'created_at' => $record->created_at,
                'docs_table_id' => "users/{$record->user_id}/completion_id/{$record->completion_id}/id_hash/{$idHash}/image_hash/{$imageHash}",
            ];
        });

        $mergedDocuments = $documentsA->concat($documentsB)->concat($documentsC)->concat($documentsD)->concat($documentsE);
        $mergedDocuments = $mergedDocuments->unique('docs_table_id');

        $page = (!empty(@$_GET['page'])) ? $_GET['page'] : 1;
        $mergedDocuments = $this->paginate_array($mergedDocuments, 8, $page, ['path' => request()->url() . '?phone=' . $phone]);

        $query = Survey::select(
            'surveys.*',
            'questionsets.title',
            'questionsets.id as questionSetId',
            'questionsets.type as questionSetType',
            'users.first_name',
            'users.last_name'
        )
            ->join('questionsets', 'questionsets.id', '=', 'surveys.formId')
            ->join('users', 'users.id', '=', 'surveys.userId')
            ->where('questionsets.businessId', '=', $company->id)
            ->where('surveys.userId', '=', $client->id);

        // Question set filter
        if ($request->has('question_set') && !empty($request->question_set)) {
            $query->where('questionsets.title', 'LIKE', '%' . $request->question_set . '%');
        }

        // Status filter
        if ($request->has('application_status') && !empty($request->application_status)) {
            $query->where('surveys.application_status', $request->application_status);
        }

        $surveys = $query->orderBy('surveys.created_at', 'desc')->paginate();

        $extraDetails = ClientExtraDetail::where('user_id', $client->id)->first();

        $extraDetailsFields = $extraDetails ? json_decode($extraDetails->additional_fields, true) : [];

        return view('client.info', [
            'phone' => $client->phone,
            'clientName' => $client->first_name . ' ' . $client->last_name,
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
