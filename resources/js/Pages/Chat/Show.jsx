import React, { useEffect, useMemo, useRef, useState } from 'react';
import { usePage, Link, router } from '@inertiajs/react';
import AppLayout from '../Layout/AppLayout';
import axios from 'axios';

function formatTime(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function useLongPress(callback, ms = 380) {
    const t = useRef(null);
    const start = (e) => {
        e.persist?.();
        t.current = setTimeout(() => callback(e), ms);
    };
    const clear = () => {
        if (t.current) clearTimeout(t.current);
        t.current = null;
    };
    return { onMouseDown: start, onTouchStart: start, onMouseUp: clear, onMouseLeave: clear, onTouchEnd: clear, onTouchCancel: clear };
}

export default function ChatShow() {
    const { props, url } = usePage();
    const roomUuid = props.roomUuid;
    const roomId = useMemo(() => {
        const parts = (url || '').split('/');
        return parts[parts.length - 1];
    }, [url]);
    const me = window?.Laravel?.userId;

    const [loading, setLoading] = useState(true);
    const [messages, setMessages] = useState([]);
    const [typing, setTyping] = useState(false);
    const [seen, setSeen] = useState(null);
    const [text, setText] = useState('');
    const [replyTo, setReplyTo] = useState(null);
    const [actionMsg, setActionMsg] = useState(null);

    const boxRef = useRef(null);

    const scrollToBottom = () => {
        const el = boxRef.current;
        if (!el) return;
        el.scrollTop = el.scrollHeight;
    };

    const fetchMessages = async () => {
        setLoading(true);
        try {
            const res = await axios.get(`/chat/${roomId}/messages`, { headers: { Accept: 'application/json' } });
            setMessages(res.data.messages ?? []);
            setTimeout(scrollToBottom, 0);
        } finally {
            setLoading(false);
        }
    };

    const fetchSeen = async () => {
        try {
            const res = await axios.get(`/chat/${roomId}/seen-status`, { headers: { Accept: 'application/json' } });
            setSeen(res.data);
        } catch {}
    };

    useEffect(() => {
        fetchMessages().then(fetchSeen);
    }, []);

    useEffect(() => {
        if (!window.Echo || !roomUuid) return;
        const channel = window.Echo.private(`chat.room.${roomUuid}`);

        channel.listen('.message.sent', (e) => {
            const msg = e.message;
            setMessages((prev) => {
                if (prev.some((m) => m.id === msg.id)) return prev;
                return [...prev, msg];
            });
            setTimeout(scrollToBottom, 0);
        });

        channel.listen('.typing.updated', (e) => {
            if (e.userId === me) return;
            setTyping(!!e.typing);
        });

        channel.listen('.seen.updated', (e) => {
            // Receiver read sender's messages
            setSeen({ id: e.messageId, read_at: e.readAt });
        });

        channel.listen('.reaction.updated', (e) => {
            setMessages((prev) => prev.map((m) => {
                if (m.id !== e.messageId) return m;
                return {
                    ...m,
                    heart_count: e.heartCount,
                    my_hearted: m.user_id !== me ? m.my_hearted : m.my_hearted, // keep
                };
            }));
        });

        channel.listen('.message.deleted', (e) => {
            setMessages((prev) => prev.map((m) => (m.id === e.messageId ? { ...m, body: null, deleted_at: new Date().toISOString() } : m)));
        });

        channel.listen('.chat.closed', () => {
            // just bounce back to chat list
            router.visit('/app/chats');
        });

        return () => {
            window.Echo.leave(`chat.room.${roomUuid}`);
        };
    }, [roomUuid]);

    // Typing signal (debounced)
    const typingTimer = useRef(null);
    const sendTyping = (isTyping) => {
        axios.post(`/chat/${roomId}/typing`, { typing: isTyping }, { headers: { Accept: 'application/json' } }).catch(() => {});
    };

    const onChange = (v) => {
        setText(v);
        sendTyping(true);
        if (typingTimer.current) clearTimeout(typingTimer.current);
        typingTimer.current = setTimeout(() => sendTyping(false), 900);
    };

    const send = async () => {
        const body = text.trim();
        if (!body) return;
        const tempId = `tmp-${Date.now()}`;
        const optimistic = {
            id: tempId,
            user_id: me,
            body,
            created_at: new Date().toISOString(),
            heart_count: 0,
            my_hearted: false,
            reply_to_id: replyTo?.id ?? null,
            reply_to: replyTo ? { id: replyTo.id, user_id: replyTo.user_id, body_preview: (replyTo.body || '').slice(0, 140) } : null,
        };
        setMessages((p) => [...p, optimistic]);
        setText('');
        setReplyTo(null);
        setTimeout(scrollToBottom, 0);

        try {
            const res = await axios.post(`/chat/${roomId}/send`, { body, reply_to_id: replyTo?.id ?? null }, { headers: { Accept: 'application/json' } });
            const real = res.data.message;
            setMessages((prev) => prev.map((m) => (m.id === tempId ? real : m)));
            fetchSeen();
        } catch (e) {
            setMessages((prev) => prev.filter((m) => m.id !== tempId));
            alert('Send failed');
        }
    };

    const toggleHeart = async (msg) => {
        try {
            const res = await axios.post(`/chat/${roomId}/message/${msg.id}/react`, {}, { headers: { Accept: 'application/json' } });
            setMessages((prev) => prev.map((m) => (m.id === msg.id ? { ...m, heart_count: res.data.heart_count, my_hearted: res.data.hearted } : m)));
        } catch {}
    };

    const deleteMine = async (msg) => {
        try {
            await axios.delete(`/chat/${roomId}/message/${msg.id}`, { headers: { Accept: 'application/json' } });
            setMessages((prev) => prev.map((m) => (m.id === msg.id ? { ...m, body: null, deleted_at: new Date().toISOString() } : m)));
        } catch {}
    };

    const closeChat = async () => {
        if (!confirm('Delete/close this chat?')) return;
        try {
            await axios.post(`/chat/${roomId}/delete-chat`, {}, { headers: { Accept: 'application/json' } });
            window.location.href = '/app/chats';
        } catch {}
    };

    return (
        <AppLayout
            title={
                <div className="flex items-center gap-3">
                    <Link href="/app/chats" className="text-sm text-gray-600 hover:text-black">Back</Link>
                    <div>Chat</div>
                    {typing ? <div className="text-xs text-gray-500">typing…</div> : null}
                </div>
            }
            fabHref={null}
        >
            <div className="bg-white rounded-2xl border shadow-sm overflow-hidden">
                <div ref={boxRef} className="h-[62vh] sm:h-[70vh] overflow-y-auto p-3 space-y-2 bg-gray-50">
                    {loading ? <div className="text-sm text-gray-500">Loading…</div> : null}
                    {messages.map((m) => {
                        const mine = m.user_id === me;
                        const deleted = !!m.deleted_at || m.body === null;
                        const lp = useLongPress(() => setActionMsg(m));
                        return (
                            <div key={m.id} className={`flex ${mine ? 'justify-end' : 'justify-start'}`}>
                                <div {...lp} className={`max-w-[82%] rounded-2xl px-3 py-2 shadow-sm ${mine ? 'bg-black text-white' : 'bg-white text-gray-900'} ${deleted ? 'opacity-60' : ''}`}>
                                    {m.reply_to ? (
                                        <div className={`mb-2 text-xs rounded-xl px-2 py-1 ${mine ? 'bg-white/10' : 'bg-gray-100'}`}>
                                            <div className="opacity-80">Reply</div>
                                            <div className="line-clamp-2">{m.reply_to.body_preview ?? 'Message removed'}</div>
                                        </div>
                                    ) : null}

                                    <div className="text-sm whitespace-pre-wrap break-words">
                                        {deleted ? <span className="italic">Message removed</span> : m.body}
                                    </div>

                                    <div className={`mt-1 flex items-center justify-end gap-2 text-[11px] ${mine ? 'text-white/70' : 'text-gray-500'}`}>
                                        {m.heart_count > 0 ? <span className="flex items-center gap-1">❤️ {m.heart_count}</span> : null}
                                        <span>{formatTime(m.created_at)}</span>
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                    {/* Seen indicator */}
                    {seen?.read_at ? (
                        <div className="text-xs text-gray-400 text-right pr-1">Seen</div>
                    ) : null}
                </div>

                {replyTo ? (
                    <div className="px-3 py-2 border-t bg-white">
                        <div className="flex items-center justify-between gap-2">
                            <div className="text-xs text-gray-600">
                                Replying to: <span className="font-medium">{(replyTo.body || '').slice(0, 80) || 'Message removed'}</span>
                            </div>
                            <button onClick={() => setReplyTo(null)} className="text-xs underline">Cancel</button>
                        </div>
                    </div>
                ) : null}

                <div className="p-3 border-t bg-white">
                    <div className="flex items-end gap-2">
                        <button onClick={closeChat} className="px-3 py-2 rounded-xl bg-gray-100 text-sm">⋯</button>
                        <textarea
                            value={text}
                            onChange={(e) => onChange(e.target.value)}
                            placeholder="Type…"
                            rows={1}
                            className="flex-1 resize-none rounded-2xl border-gray-300 focus:border-gray-400 focus:ring-0"
                            style={{ maxHeight: 120 }}
                        />
                        <button onClick={send} className="px-4 py-2 rounded-2xl bg-black text-white text-sm active:scale-[0.98] transition">Send</button>
                    </div>
                </div>
            </div>

            {/* Action sheet */}
            {actionMsg ? (
                <div className="fixed inset-0 z-50 bg-black/40" onClick={() => setActionMsg(null)}>
                    <div className="absolute bottom-0 left-0 right-0 bg-white rounded-t-3xl p-4" onClick={(e) => e.stopPropagation()}>
                        <div className="w-12 h-1 rounded-full bg-gray-200 mx-auto mb-3" />
                        <div className="space-y-2">
                            <button
                                onClick={() => {
                                    setReplyTo(actionMsg);
                                    setActionMsg(null);
                                }}
                                className="w-full text-left px-4 py-3 rounded-2xl bg-gray-50"
                            >
                                Reply
                            </button>
                            <button
                                onClick={() => {
                                    toggleHeart(actionMsg);
                                    setActionMsg(null);
                                }}
                                className="w-full text-left px-4 py-3 rounded-2xl bg-gray-50"
                            >
                                Heart
                            </button>
                            {actionMsg.user_id === me ? (
                                <button
                                    onClick={() => {
                                        deleteMine(actionMsg);
                                        setActionMsg(null);
                                    }}
                                    className="w-full text-left px-4 py-3 rounded-2xl bg-gray-50 text-red-600"
                                >
                                    Delete
                                </button>
                            ) : null}
                            <button onClick={() => setActionMsg(null)} className="w-full px-4 py-3 rounded-2xl bg-black text-white">Close</button>
                        </div>
                    </div>
                </div>
            ) : null}
        </AppLayout>
    );
}
