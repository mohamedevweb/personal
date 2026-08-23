import type {SceneName} from './timing';
import type {CutLanguage} from './copy';

/**
 * The voiceover, written sparse on purpose.
 *
 * The film is silent-first: every claim is already on screen as type, so the
 * voice does not read the screen aloud. It says the few things the type cannot —
 * the category, the belief, and the promise — and stays out of the way for the
 * rest. Roughly sixty words across thirty-three seconds.
 *
 * Each line is placed against its own shot rather than mixed into one long
 * track, so retiming a shot in `timing.ts` moves the line with it instead of
 * pulling the whole read out of sync.
 */

/**
 * Extension of the files under public/voice/<language>/. WAV because the scratch
 * read was produced locally; change this one line when the clips are replaced
 * with mp3s from a real read.
 */
export const VOICE_FORMAT = 'wav';

export type VoiceLine = {
  /** The shot this line belongs to. */
  scene: SceneName;
  /** Frames after that shot's first frame at which the line starts. */
  offset: number;
  /** Basename under public/voice/<language>/. */
  file: string;
  /** What is said. Regenerate the audio from this. */
  text: string;
};

const en: VoiceLine[] = [
  {
    scene: 'coldOpen',
    offset: 36,
    file: '01-category',
    text: 'Claude for personal brand.',
  },
  {
    scene: 'claim',
    offset: 6,
    file: '02-claim',
    text: "You're a creator. Not a content machine.",
  },
  {
    scene: 'understand',
    offset: 10,
    file: '03-understand',
    text: 'It reads your account, and tells you what it found.',
  },
  {
    scene: 'discover',
    offset: 10,
    file: '04-discover',
    text: 'Every morning, the posts beating the account that published them.',
  },
  {
    scene: 'connect',
    offset: 14,
    file: '05-connect',
    text: 'Held against something that actually happened to you.',
  },
  {
    scene: 'write',
    offset: 12,
    file: '06-write',
    text: 'Your voice. Your story. A draft, never a post.',
  },
  {
    scene: 'endCard',
    offset: 28,
    file: '07-end',
    text: 'Content only you could post.',
  },
];

const fr: VoiceLine[] = [
  {scene: 'coldOpen', offset: 36, file: '01-category', text: 'Claude pour la marque personnelle.'},
  {scene: 'claim', offset: 6, file: '02-claim', text: 'Tu es créateur. Pas une machine à contenu.'},
  {
    scene: 'understand',
    offset: 10,
    file: '03-understand',
    text: 'Il lit ton compte, et te dit ce qu’il a compris.',
  },
  {
    scene: 'discover',
    offset: 10,
    file: '04-discover',
    text: 'Chaque matin, les posts qui battent le compte qui les a publiés.',
  },
  {
    scene: 'connect',
    offset: 14,
    file: '05-connect',
    text: 'Tenus contre une histoire que tu as vraiment vécue.',
  },
  {
    scene: 'write',
    offset: 12,
    file: '06-write',
    text: 'Ta voix. Ton histoire. Un brouillon, jamais un post.',
  },
  {scene: 'endCard', offset: 28, file: '07-end', text: 'Le contenu que toi seul pouvais publier.'},
];

const scripts: Record<CutLanguage, VoiceLine[]> = {en, fr};

export const voiceoverFor = (language: CutLanguage): VoiceLine[] => scripts[language];
