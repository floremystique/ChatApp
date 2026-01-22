import React, { useEffect, useState } from 'react';
import AppLayout from '../Layout/AppLayout';
import axios from 'axios';
import { router } from '@inertiajs/react';

export default function MatchesIndex() {
    const [loading, setLoading] = useState(true);
    const [users, setUsers] = useState([]);
    const [error, setError] = useState(null);

    const fetchMatches = async () => {
        setLoading(true);
        setError(null);
        try {
            const res = await axios.get('/match', { headers: { Accept: 'application/json' } });
            setUsers(res.data.users ?? []);
        } catch (e) {
            setError('Could not load matches');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchMatches();
    }, []);

    const startChat = async (userId) => {
        try {
            const res = await axios.post(`/match/start/${userId}`, {}, { headers: { Accept: 'application/json' } });
            const roomId = res.data.room_id;
            if (roomId) {
                router.visit(`/app/chat/${roomId}`);
            }
        } catch (e) {
            alert('Could not start chat');
        }
    };

    return (
        <AppLayout title="Matches" fabHref={null}>
            {loading ? (
                <div className="text-sm text-gray-500">Loading...</div>
            ) : error ? (
                <div className="text-sm text-red-600">{error}</div>
            ) : users.length === 0 ? (
                <div className="bg-white rounded-2xl border shadow-sm p-5">
                    <div className="font-semibold">No matches yet</div>
                    <div className="text-sm text-gray-600 mt-1">Finish your profile and tags to improve matching.</div>
                </div>
            ) : (
                <div className="space-y-3">
                    {users.slice(0, 30).map((u) => (
                        <div key={u.user_id} className="bg-white rounded-2xl border shadow-sm p-4">
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <div className="font-semibold text-lg">{u.user_name}</div>
                                    <div className="text-xs text-gray-500 mt-0.5">{u.score_label} · {u.score}/100</div>
                                </div>
                                <button
                                    onClick={() => startChat(u.user_id)}
                                    className="px-4 py-2 rounded-xl bg-black text-white text-sm active:scale-[0.98] transition"
                                >
                                    Chat
                                </button>
                            </div>

                            <div className="mt-3 flex flex-wrap gap-2">
                                {(u.bond_chips ?? []).slice(0, 6).map((c, i) => (
                                    <span key={i} className="px-3 py-1 rounded-full bg-gray-100 text-xs text-gray-700">{c}</span>
                                ))}
                            </div>

                            {u.reasons?.length ? (
                                <div className="mt-2 text-xs text-gray-500">
                                    {u.reasons.slice(0, 3).join(' · ')}
                                </div>
                            ) : null}
                        </div>
                    ))}
                </div>
            )}
        </AppLayout>
    );
}
