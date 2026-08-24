import React from 'react';

/**
 * The handful of glyphs the feed mock needs, drawn at a single hairline weight
 * so the dashboard reads as one drawing rather than a collection of imports.
 * All of them inherit `color`; none of them carries the signature.
 */

type IconProps = {size: number; color: string; strokeScale?: number};

const stroke = (size: number, scale = 1) => (size / 24) * 1.7 * scale;

const Svg: React.FC<IconProps & {children: React.ReactNode; fill?: string}> = ({
  size,
  color,
  children,
  fill = 'none',
  strokeScale,
}) => (
  <svg
    width={size}
    height={size}
    viewBox="0 0 24 24"
    fill={fill}
    stroke={color}
    strokeWidth={stroke(size, strokeScale)}
    strokeLinecap="round"
    strokeLinejoin="round"
    aria-hidden
    focusable="false"
  >
    {children}
  </svg>
);

export const HeartIcon: React.FC<IconProps> = (p) => (
  <Svg {...p}>
    <path d="M20.8 8.6c0 4.4-8.8 9.6-8.8 9.6S3.2 13 3.2 8.6a4.6 4.6 0 0 1 8.8-1.8 4.6 4.6 0 0 1 8.8 1.8Z" />
  </Svg>
);

export const CommentIcon: React.FC<IconProps> = (p) => (
  <Svg {...p}>
    <path d="M20.5 11.6a7.9 7.9 0 0 1-11.7 7L3.5 20.5l1.9-5.3a7.9 7.9 0 1 1 15.1-3.6Z" />
  </Svg>
);

export const SendIcon: React.FC<IconProps> = (p) => (
  <Svg {...p}>
    <path d="M21 3 10.5 13.5" />
    <path d="M21 3l-6.8 18-3.7-7.5L3 9.8 21 3Z" />
  </Svg>
);

export const BookmarkIcon: React.FC<IconProps> = (p) => (
  <Svg {...p}>
    <path d="M6 3.8h12v17l-6-4.3-6 4.3v-17Z" />
  </Svg>
);

export const ReelIcon: React.FC<IconProps> = (p) => (
  <Svg {...p}>
    <rect x="3.2" y="3.2" width="17.6" height="17.6" rx="4.4" />
    <path d="M8.4 3.4 11.6 9M14.4 3.4 17.6 9M3.4 9h17.2" />
    <path d="m10.4 12.6 4.4 2.5-4.4 2.5v-5Z" />
  </Svg>
);

export const GearIcon: React.FC<IconProps> = (p) => (
  <Svg {...p}>
    <circle cx="12" cy="12" r="3.1" />
    <path d="M12 2.8v2.4M12 18.8v2.4M21.2 12h-2.4M5.2 12H2.8M18.5 5.5l-1.7 1.7M7.2 16.8l-1.7 1.7M18.5 18.5l-1.7-1.7M7.2 7.2 5.5 5.5" />
  </Svg>
);

export const PlusIcon: React.FC<IconProps> = (p) => (
  <Svg {...p} strokeScale={1.25}>
    <path d="M12 5.4v13.2M5.4 12h13.2" />
  </Svg>
);

export const SparkIcon: React.FC<IconProps> = (p) => (
  <Svg {...p}>
    <path d="M12 3.4 13.7 9l5.6 1.7-5.6 1.7L12 18l-1.7-5.6L4.7 10.7 10.3 9 12 3.4Z" />
  </Svg>
);

export const TrendIcon: React.FC<IconProps> = (p) => (
  <Svg {...p}>
    <path d="M3.6 16.4 9.2 10.8l3.6 3.6 7-7" />
    <path d="M15.2 7.4h4.6V12" />
  </Svg>
);

/** Sidebar glyphs, deliberately plainer than the feed's. */
export const PenIcon: React.FC<IconProps> = (p) => (
  <Svg {...p}>
    <path d="M4 20h4L19.2 8.8a2.6 2.6 0 0 0-3.7-3.7L4.3 16.3 4 20Z" />
  </Svg>
);

export const DocIcon: React.FC<IconProps> = (p) => (
  <Svg {...p}>
    <path d="M6.4 3.4h7.4L18.6 8v12.6H6.4V3.4Z" />
    <path d="M13.6 3.6V8.2h4.8" />
  </Svg>
);

export const NoteIcon: React.FC<IconProps> = (p) => (
  <Svg {...p}>
    <rect x="4.4" y="3.6" width="15.2" height="16.8" rx="2.4" />
    <path d="M8.4 8.6h7.2M8.4 12.4h7.2M8.4 16.2h4.4" />
  </Svg>
);

export const PersonIcon: React.FC<IconProps> = (p) => (
  <Svg {...p}>
    <circle cx="12" cy="8.4" r="3.6" />
    <path d="M5.2 20.2a6.8 6.8 0 0 1 13.6 0" />
  </Svg>
);
