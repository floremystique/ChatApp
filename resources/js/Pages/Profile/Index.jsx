import React from 'react';
import AppLayout from '../Layout/AppLayout';
import { Link } from '@inertiajs/react';

export default function ProfileIndex() {
    return (
        <AppLayout title="Me" fabHref={null}>
            <div className="bg-white rounded-2xl border shadow-sm p-5">
                <div className="font-semibold text-lg">Profile</div>
                <div className="text-sm text-gray-600 mt-1">Edit your profile to improve match quality.</div>
                <div className="mt-4 flex gap-2">
                    <Link href="/profile" className="px-4 py-2 rounded-xl bg-black text-white text-sm">Edit profile</Link>
                    <Link href="/onboarding" className="px-4 py-2 rounded-xl bg-gray-100 text-sm">Onboarding</Link>
                </div>
            </div>
        </AppLayout>
    );
}
