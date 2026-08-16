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

export default function ResetPassword({
    token,
    email,
}: {
    token: string;
    email: string;
}) {
    const form = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    return (
        <main className="grid min-h-screen place-items-center bg-muted/30 px-4 py-10">
            <Head title="Set new password" />
            <Card className="w-full max-w-md">
                <CardHeader>
                    <CardTitle>Set a new password</CardTitle>
                    <CardDescription>
                        Use a unique password you do not reuse elsewhere.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post('/reset-password');
                        }}
                        className="flex flex-col gap-5"
                    >
                        <FieldGroup>
                            <Field data-invalid={Boolean(form.errors.email)}>
                                <FieldLabel htmlFor="email">Email</FieldLabel>
                                <Input
                                    id="email"
                                    type="email"
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
                            <Field data-invalid={Boolean(form.errors.password)}>
                                <FieldLabel htmlFor="password">
                                    New password
                                </FieldLabel>
                                <Input
                                    id="password"
                                    type="password"
                                    autoComplete="new-password"
                                    value={form.data.password}
                                    onChange={(event) =>
                                        form.setData(
                                            'password',
                                            event.target.value,
                                        )
                                    }
                                    aria-invalid={Boolean(form.errors.password)}
                                />
                                {form.errors.password && (
                                    <FieldError>
                                        {form.errors.password}
                                    </FieldError>
                                )}
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="password_confirmation">
                                    Confirm password
                                </FieldLabel>
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    autoComplete="new-password"
                                    value={form.data.password_confirmation}
                                    onChange={(event) =>
                                        form.setData(
                                            'password_confirmation',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                        </FieldGroup>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Saving…' : 'Save password'}
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </main>
    );
}
