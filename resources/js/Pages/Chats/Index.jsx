import React, { useEffect, useMemo, useState } from 'react';
import AppLayout from '../Layout/AppLayout';
import axios from 'axios';
import { Link } from '@inertiajs/react';

function timeAgo(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    const s = Math.floor((Date.now() - d.getTime()) / 1000);
    if (s < 60) return 'now';
    const m = Math.floor(s / 60);
    if (m < 60) return `${m}m`;
    const h = Math.floor(m / 60);
    if (h < 24) return `${h}h`;
    const days = Math.floor(h / 24);
    return `${days}d`;
}

export default function ChatsIndex() {
    const [loading, setLoading] = useState(true);
    const [rooms, setRooms] = useState([]);
    const [error, setError] = useState(null);

    const fetchRooms = async () => {
        setLoading(true);
        setError(null);
        try {
            const res = await axios.get('/chats', { headers: { Accept: 'application/json' } });
            setRooms(res.data.rooms ?? []);
        } catch (e) {
            setError('Could not load chats');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchRooms();
    }, []);

    useEffect(() => {
        const me = window?.Laravel?.userId;
        // We set window.Laravel.userId from blade? If not present, skip
        if (!me || !window.Echo) return;

        const channel = window.Echo.private(`user.${me}`);
        channel.listen('.chatlist.updated', (e) => {
            const next = e.room;
            setRooms((prev) => {
                const idx = prev.findIndex((r) => r.uuid === next.uuid);
                if (idx === -1) return [next, ...prev];
                const copy = [...prev];
                copy[idx] = { ...copy[idx], ...next };
                // move to top
                const moved = copy.splice(idx, 1)[0];
                return [moved, ...copy];
            });
        });

        return () => {
            window.Echo.leave(`user.${me}`);
        };
    }, []);

    const sorted = useMemo(() => {
        return [...rooms].sort((a, b) => {
            const at = a?.last_message?.created_at ? new Date(a.last_message.created_at).getTime() : 0;
            const bt = b?.last_message?.created_at ? new Date(b.last_message.created_at).getTime() : 0;
            return bt - at;
        });
    }, [rooms]);

    return (
        <AppLayout title="Chats" fabHref="/app/matches" fabLabel="Find matches">
            {loading ? (
                <div className="text-sm text-gray-500">Loading...</div>
            ) : error ? (
                <div className="text-sm text-red-600">{error}</div>
            ) : sorted.length === 0 ? (
                <div className="bg-white rounded-2xl shadow-sm border p-5">
                    <div className="font-semibold">No chats yet</div>
                    <div className="text-sm text-gray-600 mt-1">Find a match and start a 1:1 chat.</div>
                    <Link href="/app/matches" className="inline-block mt-3 text-sm underline">Find matches</Link>
                </div>
            ) : (
                <div className="space-y-2">
                    {sorted.map((r) => (
                        <Link
                            key={r.uuid}
                            href={`/app/chat/${r.id}`}
                            className="block bg-white rounded-2xl border shadow-sm px-4 py-3 active:scale-[0.99] transition"
                        >
                            <div className="flex items-center justify-between">
                                <div className="font-semibold">{r.other_user?.name ?? 'User'}</div>
                                <div className="text-xs text-gray-500">{timeAgo(r.last_message?.created_at)}</div>
                            </div>
                            <div className="mt-1 flex items-center justify-between gap-3">
                                <div className="text-sm text-gray-600 line-clamp-1">
                                    {r.last_message?.body ?? (r.closed_at ? 'Chat closed' : 'Say hi')}
                                </div>
                                {r.unread_count > 0 ? (
                                    <div className="min-w-6 h-6 rounded-full bg-black text-white text-xs flex items-center justify-center px-2">
                                        {r.unread_count}
                                    </div>
                                ) : null}
                            </div>
                        </Link>
                    ))}
                </div>
            )}
        </AppLayout>
    );
}
