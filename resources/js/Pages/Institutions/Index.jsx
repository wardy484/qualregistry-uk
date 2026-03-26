import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

function SummaryCards({ typeCounts }) {
    if (!typeCounts.length) {
        return (
            <div className="rounded-xl border border-dashed border-slate-300 bg-white p-6 text-sm text-slate-500">
                No summary data yet. Import institutions to populate these
                cards.
            </div>
        );
    }

    return (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            {typeCounts.slice(0, 4).map((item) => (
                <div
                    key={item.institution_type}
                    className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
                >
                    <p className="text-xs font-medium uppercase tracking-wide text-slate-500">
                        {item.institution_type}
                    </p>
                    <p className="mt-2 text-2xl font-semibold text-slate-900">
                        {item.total.toLocaleString()}
                    </p>
                </div>
            ))}
        </div>
    );
}

export default function InstitutionsIndex({
    filters,
    institutions,
    typeCounts,
    typeOptions,
}) {
    const [form, setForm] = useState({
        search: filters.search ?? '',
        institution_type: filters.institution_type ?? '',
    });
    const [loading, setLoading] = useState(false);

    const submitFilters = (event) => {
        event.preventDefault();
        setLoading(true);

        router.get(route('institutions.index'), form, {
            preserveState: true,
            replace: true,
            onFinish: () => setLoading(false),
        });
    };

    const clearFilters = () => {
        const next = { search: '', institution_type: '' };
        setForm(next);
        setLoading(true);

        router.get(route('institutions.index'), next, {
            preserveState: true,
            replace: true,
            onFinish: () => setLoading(false),
        });
    };

    return (
        <>
            <Head title="Browse Institutions" />

            <div className="min-h-screen bg-slate-50">
                <div className="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                    <header className="space-y-2">
                        <h1 className="text-3xl font-bold tracking-tight text-slate-900">
                            Institutions
                        </h1>
                        <p className="text-sm text-slate-600">
                            Browse imported institutions with quick filtering by
                            name and type.
                        </p>
                    </header>

                    <SummaryCards typeCounts={typeCounts} />

                    <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                        <form
                            onSubmit={submitFilters}
                            className="grid gap-3 md:grid-cols-[1fr_240px_auto_auto] md:items-end"
                        >
                            <label className="space-y-1">
                                <span className="text-sm font-medium text-slate-700">
                                    Search by institution name
                                </span>
                                <input
                                    value={form.search}
                                    onChange={(event) =>
                                        setForm((current) => ({
                                            ...current,
                                            search: event.target.value,
                                        }))
                                    }
                                    placeholder="e.g. Oakfield"
                                    className="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </label>

                            <label className="space-y-1">
                                <span className="text-sm font-medium text-slate-700">
                                    Institution type
                                </span>
                                <select
                                    value={form.institution_type}
                                    onChange={(event) =>
                                        setForm((current) => ({
                                            ...current,
                                            institution_type:
                                                event.target.value,
                                        }))
                                    }
                                    className="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="">All types</option>
                                    {typeOptions.map((type) => (
                                        <option key={type} value={type}>
                                            {type}
                                        </option>
                                    ))}
                                </select>
                            </label>

                            <button
                                type="submit"
                                disabled={loading}
                                className="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {loading ? 'Loading…' : 'Apply'}
                            </button>

                            <button
                                type="button"
                                onClick={clearFilters}
                                disabled={loading}
                                className="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                Reset
                            </button>
                        </form>
                    </section>

                    <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        {institutions.data.length === 0 ? (
                            <div className="p-10 text-center">
                                <p className="text-lg font-medium text-slate-800">
                                    No institutions found
                                </p>
                                <p className="mt-2 text-sm text-slate-500">
                                    Try changing your filters or importing more
                                    data.
                                </p>
                            </div>
                        ) : (
                            <>
                                <div className="overflow-x-auto">
                                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                                        <thead className="bg-slate-50">
                                            <tr>
                                                <th className="px-4 py-3 text-left font-semibold text-slate-600">
                                                    Name
                                                </th>
                                                <th className="px-4 py-3 text-left font-semibold text-slate-600">
                                                    Type
                                                </th>
                                                <th className="px-4 py-3 text-left font-semibold text-slate-600">
                                                    Region
                                                </th>
                                                <th className="px-4 py-3 text-left font-semibold text-slate-600">
                                                    Postcode
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-200 bg-white">
                                            {institutions.data.map((row) => (
                                                <tr key={row.id}>
                                                    <td className="px-4 py-3 font-medium text-slate-900">
                                                        {row.name}
                                                    </td>
                                                    <td className="px-4 py-3 text-slate-700">
                                                        {row.institution_type}
                                                    </td>
                                                    <td className="px-4 py-3 text-slate-700">
                                                        {row.region ?? '—'}
                                                    </td>
                                                    <td className="px-4 py-3 text-slate-700">
                                                        {row.postcode ?? '—'}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>

                                <div className="flex flex-wrap gap-2 border-t border-slate-200 p-4">
                                    {institutions.links.map((link, index) => (
                                        <Link
                                            key={`${link.label}-${index}`}
                                            href={link.url ?? '#'}
                                            preserveState
                                            className={`rounded-md px-3 py-1.5 text-sm ${
                                                link.active
                                                    ? 'bg-slate-900 text-white'
                                                    : 'border border-slate-300 text-slate-700 hover:bg-slate-100'
                                            } ${
                                                !link.url
                                                    ? 'pointer-events-none opacity-40'
                                                    : ''
                                            }`}
                                            dangerouslySetInnerHTML={{
                                                __html: link.label,
                                            }}
                                        />
                                    ))}
                                </div>
                            </>
                        )}
                    </section>
                </div>
            </div>
        </>
    );
}
