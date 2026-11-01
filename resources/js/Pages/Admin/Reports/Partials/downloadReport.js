import { jsPDF } from 'jspdf';
import autoTable from 'jspdf-autotable';
import logo from '@/Assets/logo.png';
import { loadReportLogo } from './reportLogo';

const GREEN = [8, 137, 12];
const DARK = [30, 41, 59];
const MUTED = [107, 114, 128];

const today = () =>
    new Date().toLocaleDateString('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });

// short (letter) paper always, no legal size even for long tables
export default async function downloadReport({ subject, reviewPeriod, summaryRows, detail }) {
    const kind = detail ? 'Individual' : 'Performance Summary';
    const filename = `${subject}-${kind.replace(' ', '-')}-Report.pdf`;
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

    if (detail) {
        doc.setFontSize(11);
        doc.setTextColor(...DARK);
        doc.setFont('helvetica', 'bold');
        doc.text(`${subject} Name :`, 15, y);
        doc.setFont('helvetica', 'normal');
        doc.text(detail.name, 60, y);

        y += 8;
        doc.setFont('helvetica', 'bold');
        doc.text('Review Period :', 15, y);
        doc.setFont('helvetica', 'normal');
        doc.text(reviewPeriod, 60, y);

        y += 6;
        doc.setDrawColor(...GREEN);
        doc.line(15, y, 196, y);
        y += 10;

        doc.setFont('helvetica', 'bold');
        doc.text('Overall Rating :', 15, y);
        doc.setTextColor(...GREEN);
        doc.setFontSize(14);
        doc.text(`${detail.rating.toFixed(2)} / 4.00`, 60, y);

        y += 8;
        doc.setFontSize(11);
        doc.setTextColor(...DARK);
        doc.setFont('helvetica', 'bold');
        doc.text('Performance Status :', 15, y);
        doc.setTextColor(...GREEN);
        doc.text(detail.status.toUpperCase(), 60, y);

        y += 8;
        doc.setTextColor(...DARK);
        doc.setFont('helvetica', 'bold');
        doc.text('Responses :', 15, y);
        doc.setFont('helvetica', 'normal');
        doc.text(String(detail.responses), 60, y);

        y += 6;
        doc.setDrawColor(...GREEN);
        doc.line(15, y, 196, y);
        y += 10;

        doc.setFont('helvetica', 'bold');
        doc.text('Student Feedback :', 15, y);
        y += 7;
        doc.setFont('helvetica', 'normal');
        doc.setTextColor(...MUTED);
        const lines = doc.splitTextToSize(detail.feedback ?? '', 181);
        doc.text(lines, 15, y);
    } else {
        doc.setFontSize(10);
        doc.setTextColor(...DARK);
        doc.setFont('helvetica', 'bold');
        doc.text(reviewPeriod, 105, y, { align: 'center' });

        const head =
            subject === 'Faculty'
                ? [['Faculty Name', 'Department', 'Rating', 'Responses', 'Status']]
                : [['Office Name', 'Rating', 'Responses', 'Status']];

        const body = summaryRows.map((row) =>
            subject === 'Faculty'
                ? [row.name, row.department, row.rating.toFixed(1), row.responses, row.status]
                : [row.name, row.rating.toFixed(1), row.responses, row.status],
        );

        autoTable(doc, {
            startY: y + 6,
            head,
            body,
            headStyles: { fillColor: GREEN, textColor: 255, fontStyle: 'bold' },
            styles: { fontSize: 9, textColor: DARK },
            margin: { left: 15, right: 15 },
        });
    }

    doc.save(filename);
}
