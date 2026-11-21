import { jsPDF } from 'jspdf';
import autoTable from 'jspdf-autotable';
import logo from '@/Assets/logo.png';
import { loadReportLogo } from './reportLogo';
import { ratingLabel, summaryColumns, summaryCells, sectionColumns, sectionCells } from './reportRows';

const GREEN = [8, 137, 12];
const DARK = [30, 41, 59];
const MUTED = [107, 114, 128];
const today = () => new Date().toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });

// Use the exact same server payload and presentation helpers as the report viewer.
export async function createReportDocument({ subject, reviewPeriod, filterLabel, summaryRows, detail }) {
    const kind = detail ? 'Individual' : 'Performance Summary';
    const logoData = await loadReportLogo(logo);
    const doc = new jsPDF({ unit: 'mm', format: 'letter' });
    doc.addImage(logoData, 'JPEG', 15, 14, 20, 20);
    doc.setTextColor(...GREEN);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(16);
    doc.text(`${subject} Evaluation ${kind} Report`, 40, 23);
    doc.setTextColor(...MUTED);
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(9);
    doc.text(`Generated on: ${today()}`, 40, 29);
    doc.setDrawColor(...GREEN);
    doc.setLineWidth(1);
    doc.line(15, 40, 196, 40);
    let y = 50;
    const text = (value, bold = false) => {
        doc.setFontSize(10);
        doc.setFont('helvetica', bold ? 'bold' : 'normal');
        doc.setTextColor(...DARK);
        const lines = doc.splitTextToSize(String(value), 181);
        if (y + lines.length * 5 > 260) { doc.addPage(); y = 20; }
        doc.text(lines, 15, y);
        y += lines.length * 5 + 3;
    };
    const table = (head, body) => {
        if (y > 245) { doc.addPage(); y = 20; }
        autoTable(doc, {
            startY: y, head: [head], body,
            headStyles: { fillColor: GREEN, textColor: 255, fontStyle: 'bold' },
            styles: { fontSize: 9, textColor: DARK, overflow: 'linebreak' },
            margin: { left: 15, right: 15, top: 15, bottom: 15 },
        });
        y = doc.lastAutoTable.finalY + 8;
    };
    text(reviewPeriod, true);
    if (filterLabel) text(filterLabel);
    if (detail) {
        text(`${subject} Name: ${detail.name}`, true);
        text(subject === 'Staff'
            ? `Evaluated position: ${detail.position} | Office: ${detail.officeCode} - ${detail.officeName}` : subject === 'Faculty'
            ? `Faculty ID: ${detail.facultyId} | Department: ${detail.department ?? '—'}`
            : `Office Code: ${detail.officeCode ?? '—'}`);
        text(`Overall Rating: ${ratingLabel(detail)}${detail.rating !== null && detail.status !== 'No Data' ? ' / 4.00' : ''}`, true);
        text(`Performance Status: ${detail.status}`);
        text(`Responses: ${detail.responses === null ? 'Withheld' : detail.responses}`);
        if (detail.disclosureMessage) text(detail.disclosureMessage);
        if (detail.sections?.length) table(sectionColumns, detail.sections.map(sectionCells));
        else if (!detail.disclosureMessage) text('No rated sections for this selection.');
        text('Section averages use stored answers only. Unanswered questions are excluded. Overall rating retains the existing average of all stored answer ratings.');
        if (subject !== 'Staff') table(['Student Feedback'], [[detail.feedback || 'No feedback available.']]);
    } else if (summaryRows?.length) {
        table(summaryColumns(subject), summaryRows.map((row) => summaryCells(subject, row)));
    } else {
        text('No records match the applied filters.');
    }
    return doc;
}

// Letter paper and the existing download names are retained.
export default async function downloadReport(report, signal) {
    const doc = await createReportDocument(report);
    if (signal?.aborted) return;
    const kind = report.detail ? 'Individual' : 'Performance Summary';
    doc.save(`${report.subject}-${kind.replace(' ', '-')}-Report.pdf`);
}
