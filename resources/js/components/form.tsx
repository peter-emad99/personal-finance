import type {
    InputHTMLAttributes,
    PropsWithChildren,
    SelectHTMLAttributes,
} from 'react';

export function Field({
    label,
    ...props
}: InputHTMLAttributes<HTMLInputElement> & { label: string }) {
    return (
        <label className="block space-y-2">
            <span className="text-xs font-semibold text-[#58657a]">
                {label}
            </span>
            <input
                {...props}
                className="w-full rounded-xl border border-[#dfe3ea] bg-white px-3.5 py-2.5 text-sm text-[#202a39] transition outline-none placeholder:text-[#a9b1be] focus:border-[#7c8cf8] focus:ring-4 focus:ring-[#7c8cf8]/10"
            />
        </label>
    );
}
export function SelectField({
    label,
    children,
    ...props
}: PropsWithChildren<
    SelectHTMLAttributes<HTMLSelectElement> & { label: string }
>) {
    return (
        <label className="block space-y-2">
            <span className="text-xs font-semibold text-[#58657a]">
                {label}
            </span>
            <select
                {...props}
                className="w-full rounded-xl border border-[#dfe3ea] bg-white px-3.5 py-2.5 text-sm text-[#202a39] transition outline-none focus:border-[#7c8cf8] focus:ring-4 focus:ring-[#7c8cf8]/10"
            >
                {children}
            </select>
        </label>
    );
}
export function FormModal({
    title,
    onClose,
    children,
}: PropsWithChildren<{ title: string; onClose: () => void }>) {
    return (
        <div className="fixed inset-0 z-50 grid place-items-center bg-[#101928]/40 p-4">
            <div className="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6 shadow-2xl">
                <div className="mb-5 flex items-center justify-between">
                    <h2 className="text-lg font-semibold text-[#1c2737]">
                        {title}
                    </h2>
                    <button
                        onClick={onClose}
                        className="text-2xl leading-none text-[#8993a3] hover:text-[#1c2737]"
                    >
                        ×
                    </button>
                </div>
                {children}
            </div>
        </div>
    );
}
