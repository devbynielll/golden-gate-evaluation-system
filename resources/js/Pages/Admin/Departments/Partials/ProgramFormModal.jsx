import CodeNameFormModal from '@/Components/CodeNameFormModal';
import { PROGRAM_SUGGESTIONS } from '@/Constants/codeNameSuggestions';

// add/edit program modal
export default function ProgramFormModal({
    show,
    mode = 'add',
    program = null,
    onClose,
    onSubmit,
    errors = {},
}) {
    return (
        <CodeNameFormModal
            show={show}
            mode={mode}
            initial={program}
            onClose={onClose}
            onSubmit={onSubmit}
            errors={errors}
            titleAdd="Add Program"
            titleEdit="Edit Program"
            codeLabel="Program Code"
            nameLabel="Program Name"
            codePlaceholder="e.g. BSIT"
            namePlaceholder="e.g. Bachelor of Science in Information Technology"
            suggestions={PROGRAM_SUGGESTIONS}
        />
    );
}
