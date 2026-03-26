<?php

namespace App\Http\Controllers;

use App\Models\Institution;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class InstitutionBrowseController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $institutionType = trim((string) $request->string('institution_type'));

        try {
            $institutionsQuery = Institution::query()
                ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                ->when($institutionType !== '', fn ($query) => $query->where('institution_type', $institutionType));

            $institutions = $institutionsQuery
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString();

            $typeCounts = Institution::query()
                ->selectRaw('institution_type, COUNT(*) as total')
                ->groupBy('institution_type')
                ->orderByDesc('total')
                ->get()
                ->map(fn ($row) => [
                    'institution_type' => $row->institution_type,
                    'total' => (int) $row->total,
                ]);

            $typeOptions = Institution::query()
                ->select('institution_type')
                ->distinct()
                ->orderBy('institution_type')
                ->pluck('institution_type');

            $error = null;
        } catch (QueryException $exception) {
            report($exception);

            $institutions = new LengthAwarePaginator(
                items: [],
                total: 0,
                perPage: 15,
                currentPage: 1,
                options: ['path' => $request->url(), 'query' => $request->query()]
            );

            $typeCounts = [];
            $typeOptions = [];
            $error = 'Institutions data is not available yet. Run ingestion and migrations, then refresh this page.';
        }

        return Inertia::render('Institutions/Index', [
            'filters' => [
                'search' => $search,
                'institution_type' => $institutionType,
            ],
            'institutions' => $institutions,
            'typeCounts' => $typeCounts,
            'typeOptions' => $typeOptions,
            'error' => $error,
        ]);
    }
}
