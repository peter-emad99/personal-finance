import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';

export default function ForgotPassword() {
    const form = useForm({ email: '' });

    return (
        <main className="grid min-h-screen place-items-center bg-muted/30 px-4 py-10">
            <Head title="Recover password" />
            <Card className="w-full max-w-md">
                <CardHeader>
                    <CardTitle>Recover your password</CardTitle>
                    <CardDescription>
                        We’ll send a time-limited recovery link if the account
                        exists.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post('/forgot-password');
                        }}
                        className="flex flex-col gap-5"
                    >
                        <FieldGroup>
                            <Field data-invalid={Boolean(form.errors.email)}>
                                <FieldLabel htmlFor="email">Email</FieldLabel>
                                <Input
                                    id="email"
                                    type="email"
                                    autoComplete="email"
                                    value={form.data.email}
                                    onChange={(event) =>
                                        form.setData(
                                            'email',
                                            event.target.value,
                                        )
                                    }
                                    aria-invalid={Boolean(form.errors.email)}
                                />
                                {form.errors.email && (
                                    <FieldError>{form.errors.email}</FieldError>
                                )}
                            </Field>
                        </FieldGroup>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing
                                ? 'Sending…'
                                : 'Send recovery link'}
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </main>
    );
}
