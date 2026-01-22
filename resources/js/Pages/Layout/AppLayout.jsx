import React from 'react';
import { Link, usePage } from '@inertiajs/react';

function Icon({ name, active }) {
    const common = `w-6 h-6 ${active ? 'text-black' : 'text-gray-400'}`;
    if (name === 'chat') {
        return (
            <svg className={common} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M21 15a4 4 0 0 1-4 4H7l-4 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z" />
            </svg>
        );
    }
    if (name === 'match') {
        return (
            <svg className={common} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z" />
            </svg>
        );
    }
    return (
        <svg className={common} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
            <circle cx="12" cy="7" r="4" />
        </svg>
    );
}

export default function AppLayout({ title, children, fabHref, fabLabel = 'New' }) {
    const { url } = usePage();
    const path = url || '';
    const isChats = path.startsWith('/app/chats') || path.startsWith('/app/chat');
    const isMatches = path.startsWith('/app/matches');
    const isProfile = path.startsWith('/app/profile');

    return (
        <div className="min-h-screen bg-gray-50">
            <div className="sticky top-0 z-30 bg-white/90 backdrop-blur border-b">
                <div className="max-w-3xl mx-auto px-4 py-3 flex items-center justify-between">
                    <div className="font-semibold text-lg">{title}</div>
                    <Link href="/app/profile" className="text-sm text-gray-600 hover:text-black">
                        Profile
                    </Link>
                </div>
            </div>

            <main className="max-w-3xl mx-auto px-4 pb-24 pt-3">
                {children}
            </main>

            {fabHref ? (
                <Link
                    href={fabHref}
                    className="fixed bottom-20 right-5 z-40 w-14 h-14 rounded-full bg-black text-white shadow-lg flex items-center justify-center active:scale-95 transition"
                    aria-label={fabLabel}
                >
                    <svg className="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                        <path d="M12 5v14" />
                        <path d="M5 12h14" />
                    </svg>
                </Link>
            ) : null}

            <nav className="fixed bottom-0 left-0 right-0 z-30 bg-white border-t">
                <div className="max-w-3xl mx-auto grid grid-cols-3">
                    <Link href="/app/chats" className={`py-3 flex flex-col items-center gap-1 ${isChats ? 'text-black' : 'text-gray-500'}`}>
                        <Icon name="chat" active={isChats} />
                        <div className="text-xs">Chats</div>
                    </Link>
                    <Link href="/app/matches" className={`py-3 flex flex-col items-center gap-1 ${isMatches ? 'text-black' : 'text-gray-500'}`}>
                        <Icon name="match" active={isMatches} />
                        <div className="text-xs">Matches</div>
                    </Link>
                    <Link href="/app/profile" className={`py-3 flex flex-col items-center gap-1 ${isProfile ? 'text-black' : 'text-gray-500'}`}>
                        <Icon name="profile" active={isProfile} />
                        <div className="text-xs">Me</div>
                    </Link>
                </div>
            </nav>
        </div>
    );
}
