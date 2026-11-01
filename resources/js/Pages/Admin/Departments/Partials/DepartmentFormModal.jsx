import CodeNameFormModal from '@/Components/CodeNameFormModal';
import { DEPARTMENT_SUGGESTIONS } from '@/Constants/codeNameSuggestions';

// add/edit department modal
export default function DepartmentFormModal({
    show,
    mode = 'add',
    department = null,
    onClose,
    onSubmit,
    errors = {},
}) {
    return (
        <CodeNameFormModal
            show={show}
            mode={mode}
            initial={department}
            onClose={onClose}
            onSubmit={onSubmit}
            errors={errors}
            titleAdd="Add Department"
            titleEdit="Edit Department"
            codeLabel="Department Code"
            nameLabel="Department Name"
            codePlaceholder="e.g. CET"
            namePlaceholder="e.g. College of Engineering and Technology"
            suggestions={DEPARTMENT_SUGGESTIONS}
        />
    );
}
