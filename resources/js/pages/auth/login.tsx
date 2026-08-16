import { Head, Link, useForm } from '@inertiajs/react';
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
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';

export default function Login() {
    const form = useForm({ email: '', password: '', remember: false });

    return (
        <main className="grid min-h-screen place-items-center bg-muted/30 px-4 py-10">
            <Head title="Sign in" />
            <Card className="w-full max-w-md">
                <CardHeader>
                    <CardTitle>Personal finance OS</CardTitle>
                    <CardDescription>
                        Sign in to your private financial workspace.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post('/login');
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
                            <Field data-invalid={Boolean(form.errors.password)}>
                                <FieldLabel htmlFor="password">
                                    Password
                                </FieldLabel>
                                <Input
                                    id="password"
                                    type="password"
                                    autoComplete="current-password"
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
                                <FieldDescription>
                                    <Link
                                        href="/forgot-password"
                                        className="underline underline-offset-4"
                                    >
                                        Forgot your password?
                                    </Link>
                                </FieldDescription>
                            </Field>
                        </FieldGroup>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Signing in…' : 'Sign in'}
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </main>
    );
}
