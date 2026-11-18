import { DialogTitle } from '@headlessui/react';
import Modal from '@/Components/Modal';
import XIcon from '@/Components/Icons/XIcon';
import { ratingLabel, summaryColumns, summaryCells, sectionColumns, sectionCells } from './reportRows';

function ReportTable({ columns, rows }) {
    return (
        <div className="overflow-x-auto" tabIndex={0} role="region" aria-label={columns[0] === 'Criteria Section' ? 'Section ratings table' : 'Report summary table'}>
            <table className="w-full text-left text-sm">
                <thead><tr className="border-b border-gray-200 bg-gray-50">
                    {columns.map((column) => <th key={column} scope="col" className="px-3 py-2 text-xs font-bold text-gray-600">{column}</th>)}
                </tr></thead>
                <tbody>{rows.map((cells, index) => <tr key={index} className="border-b border-gray-100">
                    {cells.map((cell, column) => <td key={column} className="px-3 py-2 text-gray-700">{cell}</td>)}
                </tr>)}</tbody>
            </table>
        </div>
    );
}

export default function ReportViewer({ show, loading, error, report, onClose, onDownload }) {
    const detail = report?.detail;
    return (
        <Modal show={show} onClose={onClose} maxWidth="5xl">
            <div className="max-h-[85vh] min-w-0 overflow-y-auto p-5 sm:p-6" aria-busy={loading}>
                <div className="flex items-start justify-between gap-3">
                    <DialogTitle className="text-lg font-extrabold text-gray-900">
                        {report ? `${report.subject} ${detail ? 'Individual' : 'Performance Summary'} Report` : 'Report Viewer'}
                    </DialogTitle>
                    <button type="button" onClick={onClose} aria-label="Close report" className="rounded text-gray-500 hover:text-gray-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-green-600"><XIcon className="h-5 w-5" /></button>
                </div>
                {loading && <p role="status" className="mt-4 text-sm text-gray-600">Preparing report…</p>}
                {error && <p role="alert" className="mt-4 text-sm text-red-600">{error}</p>}
                {report && <>
                    <p className="mt-2 text-sm font-semibold text-green-700">{report.reviewPeriod}</p>
                    <p className="mt-1 break-words text-xs text-gray-500">{report.filterLabel}</p>
                    {report.notice && <p className="mt-3 text-xs text-yellow-800">{report.notice}</p>}
                    <div className="mt-4 space-y-4">
                        {detail ? <>
                            <div className="rounded-lg bg-gray-50 p-4">
                                <h3 className="break-words font-bold text-gray-800">{detail.name}</h3>
                                <p className="mt-1 text-sm text-gray-600">{report.subject === 'Staff' ? `Evaluated position: ${detail.position} | Office: ${detail.officeCode} — ${detail.officeName}` : report.subject === 'Faculty' ? `Faculty ID: ${detail.facultyId} | Department: ${detail.department ?? '—'}` : `Office Code: ${detail.officeCode ?? '—'}`}</p>
                                <p className="mt-2 text-sm text-gray-700">Overall Rating: <strong>{ratingLabel(detail)}{detail.rating !== null && detail.status !== 'No Data' && ' / 4.00'}</strong> · {detail.status} · Responses: {detail.responses === null ? 'Withheld' : detail.responses}</p>
                                {detail.disclosureMessage && <p role="status" className="mt-2 text-sm text-yellow-800">{detail.disclosureMessage}</p>}
                            </div>
                            <section aria-label="Section ratings">
                                <h3 className="mb-2 text-sm font-bold text-gray-800">Criteria Section Ratings</h3>
                                {detail.sections?.length ? <ReportTable columns={sectionColumns} rows={detail.sections.map(sectionCells)} /> : <p className="text-sm text-gray-500">{detail.disclosureMessage || 'No rated sections for this selection.'}</p>}
                                <p className="mt-2 text-xs text-gray-500">Section averages use stored answers only. Unanswered questions are excluded. Overall rating retains the existing average of all stored answer ratings.</p>
                            </section>
                            {report.subject !== 'Staff' && <section aria-label="Student feedback">
                                <h3 className="mb-2 text-sm font-bold text-gray-800">Student Feedback</h3>
                                <p className="whitespace-pre-wrap break-words text-sm text-gray-600">{detail.feedback || 'No feedback available.'}</p>
                            </section>}
                        </> : report.summaryRows?.length ? <ReportTable columns={summaryColumns(report.subject)} rows={report.summaryRows.map((row) => summaryCells(report.subject, row))} /> : <p role="status" className="text-sm text-gray-500">No records match the applied filters.</p>}
                    </div>
                    <button type="button" disabled={loading} onClick={onDownload} className="mt-5 rounded-md bg-green-700 px-4 py-2 text-xs font-bold text-white hover:bg-green-800 disabled:opacity-50">Download PDF</button>
                </>}
            </div>
        </Modal>
    );
}
