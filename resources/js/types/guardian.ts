export type Verdict = 'fact' | 'hoax'

export interface Guardian {
  name: string
  level: number
  rank: string
  rank_label: string
  accent: string
  xp: number
  xp_into_level: number
  xp_for_next: number
  xp_level_cost: number
  progress: number
  is_max_level: boolean
  next_rank: { value: string; label: string; min_level: number } | null
  hp: number
  max_hp: number
  hp_ratio: number
  streak: number
  best_streak: number
  solved_count: number
  accuracy: number
}

export interface QuestDifficulty {
  value: number
  key: string
  label: string
  reward: number
}

export interface Quest {
  id: string
  title: string
  source: string
  excerpt: string
  image_url: string | null
  category: string
  difficulty: QuestDifficulty
  published_at: string | null
  ai_ready: boolean
}

export interface RedFlag {
  type: string
  label: string
  note: string
}

export interface Feedback {
  headline: string
  analysis: string
  red_flags: RedFlag[]
  tip: string
  from_fallback: boolean
  model: string | null
}

export interface AttemptResult {
  correct: boolean
  chosen: Verdict
  truth: Verdict
  truth_label: string
  xp_delta: number
  hp_delta: number
  leveled_up: boolean
  ranked_up: boolean
  streak: number
  guardian: Guardian
  feedback: Feedback
  next_quest: Quest | null
}
