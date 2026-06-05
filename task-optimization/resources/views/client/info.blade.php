<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Information</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-2xl mx-auto bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-2xl font-bold text-blue-600 mb-4">Client Information</h2>
        
        <div class="mb-6">
            <p class="text-lg"><strong>Name:</strong> {{ $clientName }}</p>
            <p class="text-lg"><strong>Phone:</strong> {{ $phone }}</p>
        </div>

        <h3 class="text-xl font-semibold text-gray-700 mb-3">Documents</h3>
        <table class="w-full text-left border-collapse bg-white rounded-lg overflow-hidden shadow-sm">
            <thead>
                <tr class="bg-gray-800 text-white">
                    <th class="p-3">Document Name</th>
                    <th class="p-3">Type</th>
                </tr>
            </thead>
            <tbody>
                @forelse($documentViewsDocs as $doc)
                    <tr class="border-b border-gray-200 hover:bg-gray-50">
                        <td class="p-3 text-gray-700">{{ $doc->name }}</td>
                        <td class="p-3">
                            <span class="px-2 py-1 text-xs font-semibold rounded bg-cyan-500 text-white shadow-sm">
                                {{ $doc->type }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="p-4 text-center text-gray-500">No documents found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        
        @if($documentViewsDocs->hasPages())
            <div class="mt-4">
                {{ $documentViewsDocs->links() }}
            </div>
        @endif
    </div>
</body>
</html>