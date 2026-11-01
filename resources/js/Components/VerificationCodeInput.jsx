import { useRef } from 'react';

// single-digit OTP boxes
export default function VerificationCodeInput({
    length = 6,
    value,
    onChange,
}) {
    const inputRefs = useRef([]);

    const digits = Array.from({ length }, (_, i) => value[i] ?? '');

    const setDigit = (index, digit) => {
        const next = digits.slice();
        next[index] = digit;
        onChange(next.join(''));
    };

    const handleChange = (index, e) => {
        const digit = e.target.value.replace(/\D/g, '').slice(-1);
        setDigit(index, digit);

        if (digit && index < length - 1) {
            inputRefs.current[index + 1]?.focus();
        }
    };

    const handleKeyDown = (index, e) => {
        if (e.key === 'Backspace' && !digits[index] && index > 0) {
            inputRefs.current[index - 1]?.focus();
        }
    };

    const handlePaste = (e) => {
        const pasted = e.clipboardData
            .getData('text')
            .replace(/\D/g, '')
            .slice(0, length);

        if (!pasted) return;

        e.preventDefault();
        onChange(pasted.padEnd(length, '').slice(0, length).trimEnd());
        inputRefs.current[Math.min(pasted.length, length - 1)]?.focus();
    };

    return (
        <div className="flex justify-center gap-2">
            {digits.map((digit, index) => (
                <input
                    key={index}
                    ref={(el) => (inputRefs.current[index] = el)}
                    type="text"
                    inputMode="numeric"
                    maxLength={1}
                    value={digit}
                    onChange={(e) => handleChange(index, e)}
                    onKeyDown={(e) => handleKeyDown(index, e)}
                    onPaste={handlePaste}
                    className="h-12 w-10 rounded-md border-gray-300 text-center text-lg font-semibold text-gray-800 shadow-sm focus:border-green-600 focus:ring-green-600"
                />
            ))}
        </div>
    );
}
