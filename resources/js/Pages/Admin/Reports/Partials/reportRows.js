// Shared presentation keeps previews and PDF tables on the same labels and precision.
export const ratingLabel = (row) => row.rating === null || row.rating === undefined ? 'Withheld' : row.status === 'No Data' ? 'No Data' : Number(row.rating).toFixed(2);
export const summaryColumns = (subject) => subject === 'Staff'
    ? ['Staff Name', 'Evaluated Position', 'Office', 'Review Period', 'Rating / 4.00', 'Responses', 'Status'] : subject === 'Faculty'
    ? ['Faculty Name', 'Faculty ID', 'Department', 'Rating / 4.00', 'Responses', 'Status']
    : ['Office Name', 'Office Code', 'Rating / 4.00', 'Responses', 'Status'];
export const summaryCells = (subject, row) => [row.name,
    ...(subject === 'Staff' ? [row.position, `${row.officeCode} - ${row.officeName}`, row.reviewPeriod] : subject === 'Faculty' ? [row.facultyId, row.department ?? '—'] : [row.officeCode ?? '—']),
    ratingLabel(row), row.responses === null ? 'Withheld' : String(row.responses), row.disclosureMessage || row.status];
export const sectionColumns = ['Criteria Section', 'Rating / 4.00', 'Answered Items'];
export const sectionCells = (section) => [section.label, Number(section.rating).toFixed(2), String(section.answeredItems)];
