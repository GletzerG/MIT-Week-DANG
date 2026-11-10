export type VerdictTone = 'acid' | 'magenta' | 'amber';

export interface Guardian {
    name: string;
    level: number;
    xp: number;
    rank_label: string;
    xp_into_level: number;
    xp_level_cost: number;
    xp_for_next: number;
    progress: number;
    hp: number;
    max_hp: number;
    hp_ratio: number;
    streak: number;
    best_streak: number;
    solved_count: number;
    accuracy: number;
    accent?: string;
    next_rank?: { value: string; label: string; min_level: number } | null;
}

export interface RedFlag {
    label: string;
    note: string;
}

export interface FeedItem {
    id: string;
    title: string;
    source: string;
    author: string | null;
    excerpt: string;
    image_url: string | null;
    category: string;
    published_at: string | null;
    verdict: { value: 'fact' | 'hoax'; label: string; tone: VerdictTone };
    credibility: number;
    fact_check: { source: string | null; url: string | null };
    red_flags: RedFlag[];
    difficulty: { value: number; label: string };
}

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
}

export interface ClaimSignal {
    key: string;
    label: string;
    note: string;
    tone: 'risk' | 'trust';
}

export interface ClaimSource {
    type: 'text' | 'image' | 'link';
    ref: string | null;
    label: string | null;
    title: string | null;
    reputation: { tier: 'trusted' | 'unknown' | 'suspect'; label: string; score: number; note: string } | null;
    attribution: { author: boolean; date: boolean; site: boolean } | null;
    cues: string[];
    meta: Record<string, string | number | null>;
    text: string;
    chars: number;
    cached: boolean;
}

export interface ClaimResult {
    verdict: {
        value: 'fact' | 'hoax' | 'uncertain';
        label: string;
        headline: string;
        tone: VerdictTone;
        icon: 'check' | 'cross' | 'question';
    };
    confidence: number;
    summary: string;
    reasons: string[];
    red_flags: RedFlag[];
    recommendation: string;
    signals: ClaimSignal[];
    engine: 'ai' | 'rules';
    model: string | null;
    from_fallback: boolean;
    char_count: number;
    source: ClaimSource;
    xp_awarded: number;
    guardian: Guardian;
    quota: { used: number; cap: number };
}