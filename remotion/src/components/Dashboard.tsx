import React from 'react';
import {families} from '../fonts';
import {useLayout} from '../layout';
import {alpha, media, palette, radius, type as typeScale} from '../theme';
import {Mark} from './Mark';
import {
  BookmarkIcon,
  CommentIcon,
  DocIcon,
  GearIcon,
  HeartIcon,
  NoteIcon,
  PenIcon,
  PersonIcon,
  PlusIcon,
  ReelIcon,
  SendIcon,
  SparkIcon,
  TrendIcon,
} from './Icons';
import type {Copy, FeedPost} from '../copy';

/**
 * The product itself: the For You feed, as the creator actually sees it.
 *
 * It is a mock, not a screenshot — a render must not reach the network, and
 * Instagram's CDN refuses hotlinked media anyway — so the posts carry warm
 * washes where the photographs would be. Everything else is the real shell:
 * the same sidebar, the same card, the same words.
 *
 * The type is deliberately larger than the shipping app's. The film is watched
 * at a fraction of its render size — 1088px wide in the landing page hero — so
 * anything set at true UI scale would be texture rather than text. What has to
 * survive that reduction is the handle, the ratio pill and the two buttons;
 * those are sized up, and the rest is allowed to read as density.
 */

const NAV_ICON = 0.62;

const SidebarItem: React.FC<{
  label: string;
  icon: React.ReactNode;
  active?: boolean;
  size: number;
}> = ({label, icon, active = false, size}) => {
  const {px} = useLayout();

  return (
    <div
      style={{
        display: 'flex',
        alignItems: 'center',
        gap: px(size * 0.62),
        padding: `${px(size * 0.52)}px ${px(size * 0.7)}px`,
        borderRadius: px(radius.card * 0.8),
        backgroundColor: active ? alpha.navActive : 'transparent',
        color: active ? palette.ink : palette.muted,
        fontFamily: families.body,
        fontWeight: active ? 600 : 500,
        fontSize: px(size),
      }}
    >
      {icon}
      <span>{label}</span>
    </div>
  );
};

const SectionLabel: React.FC<{children: React.ReactNode; size: number}> = ({children, size}) => {
  const {px} = useLayout();
  return (
    <div
      style={{
        fontFamily: families.body,
        fontWeight: 600,
        fontSize: px(size),
        letterSpacing: typeScale.eyebrowTracking,
        textTransform: 'uppercase',
        color: palette.muted,
        opacity: 0.75,
      }}
    >
      {children}
    </div>
  );
};

const RoundButton: React.FC<{
  size: number;
  children: React.ReactNode;
  background: string;
  border?: boolean;
}> = ({size, children, background, border = false}) => {
  const {px} = useLayout();
  return (
    <div
      style={{
        width: px(size),
        height: px(size),
        borderRadius: '50%',
        backgroundColor: background,
        border: border ? `${Math.max(1, px(1))}px solid ${palette.line}` : 'none',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
      }}
    >
      {children}
    </div>
  );
};

const FeedCard: React.FC<{post: FeedPost; copy: Copy; index: number; width: number}> = ({
  post,
  copy,
  index,
  width,
}) => {
  const {px} = useLayout();
  const d = copy.dashboard;

  // Scale the whole card off its own width, so the same component reads at any
  // of the three aspect ratios without a second set of numbers.
  const u = width / 390;
  const s = (v: number) => px(v * u);
  const tone = media[index % media.length] ?? media[0]!;

  return (
    <div
      style={{
        width,
        backgroundColor: palette.surface,
        border: `${Math.max(1, px(1))}px solid ${palette.line}`,
        borderRadius: px(radius.card),
        overflow: 'hidden',
        display: 'flex',
        flexDirection: 'column',
      }}
    >
      <div style={{display: 'flex', alignItems: 'center', gap: s(12), padding: s(16)}}>
        <div
          style={{
            width: s(38),
            height: s(38),
            borderRadius: '50%',
            background: `linear-gradient(140deg, ${tone[1]}, ${tone[0]})`,
            border: `${Math.max(1, px(1.5))}px solid ${palette.surface}`,
            boxShadow: `0 0 0 ${Math.max(1, px(1.5))}px ${palette.line}`,
            flex: '0 0 auto',
          }}
        />
        <div style={{minWidth: 0}}>
          <div
            style={{
              fontFamily: families.body,
              fontWeight: 600,
              fontSize: s(19),
              color: palette.ink,
              whiteSpace: 'nowrap',
            }}
          >
            {post.handle}
            <span style={{color: palette.muted, fontWeight: 400}}> · {post.age}</span>
          </div>
          <div
            style={{
              fontFamily: families.body,
              fontWeight: 400,
              fontSize: s(15),
              color: palette.muted,
              marginTop: s(2),
            }}
          >
            {d.followers.replace('{count}', post.followers)}
          </div>
        </div>
      </div>

      <div
        style={{
          position: 'relative',
          height: s(224),
          background: `linear-gradient(155deg, ${tone[1]}, ${tone[0]})`,
        }}
      >
        <div style={{position: 'absolute', top: s(14), right: s(14), opacity: 0.85}}>
          <ReelIcon size={s(24)} color={palette.surface} />
        </div>
      </div>

      <div style={{padding: s(16), display: 'flex', flexDirection: 'column', gap: s(10)}}>
        <div style={{display: 'flex', alignItems: 'center', gap: s(16)}}>
          <HeartIcon size={s(26)} color={palette.ink} />
          <CommentIcon size={s(26)} color={palette.ink} />
          <SendIcon size={s(26)} color={palette.ink} />
          <div style={{marginLeft: 'auto'}}>
            <BookmarkIcon size={s(26)} color={palette.ink} />
          </div>
        </div>

        <div
          style={{
            fontFamily: families.body,
            fontWeight: 600,
            fontSize: s(18),
            color: palette.ink,
          }}
        >
          {post.likes} likes
        </div>

        <div
          style={{
            fontFamily: families.body,
            fontWeight: 400,
            fontSize: s(17),
            lineHeight: 1.4,
            color: palette.muted,
            display: '-webkit-box',
            WebkitLineClamp: 2,
            WebkitBoxOrient: 'vertical',
            overflow: 'hidden',
          }}
        >
          <span style={{fontWeight: 600, color: palette.ink}}>{post.handle} </span>
          {post.caption}
        </div>

        <div
          style={{
            fontFamily: families.body,
            fontWeight: 400,
            fontSize: s(15),
            color: palette.muted,
            opacity: 0.8,
          }}
        >
          {d.viewComments.replace('{count}', post.comments)}
        </div>

        {/* The reason the card is in the feed at all. On the one post that is
            genuinely an outlier this is the only red in the frame. */}
        <div style={{display: 'flex', alignItems: 'center', gap: s(8), marginTop: s(2)}}>
          <div
            style={{
              display: 'flex',
              alignItems: 'center',
              gap: s(7),
              padding: `${s(8)}px ${s(13)}px`,
              borderRadius: px(radius.chip),
              backgroundColor: post.lit ? alpha.signatureWash : alpha.chipIdle,
              color: post.lit ? palette.signature : palette.muted,
              fontFamily: families.body,
              fontWeight: 600,
              fontSize: s(16),
              whiteSpace: 'nowrap',
            }}
          >
            <TrendIcon size={s(17)} color={post.lit ? palette.signature : palette.muted} />
            {d.ratio.replace('{ratio}', post.ratio)}
          </div>
          <div
            style={{
              padding: `${s(8)}px ${s(13)}px`,
              borderRadius: px(radius.chip),
              backgroundColor: alpha.chipIdle,
              color: palette.muted,
              fontFamily: families.body,
              fontWeight: 500,
              fontSize: s(16),
              whiteSpace: 'nowrap',
            }}
          >
            {d.views.replace('{count}', post.views)}
          </div>
        </div>

        <div
          style={{
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            gap: s(8),
            marginTop: s(4),
            height: s(46),
            borderRadius: px(radius.chip),
            border: `${Math.max(1, px(1))}px solid ${palette.line}`,
            fontFamily: families.body,
            fontWeight: 500,
            fontSize: s(17),
            color: palette.ink,
          }}
        >
          <BookmarkIcon size={s(18)} color={palette.ink} />
          {d.save}
        </div>

        <div
          style={{
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            gap: s(8),
            height: s(50),
            borderRadius: px(radius.chip),
            backgroundColor: palette.ink,
            color: palette.ivory,
            fontFamily: families.body,
            fontWeight: 500,
            fontSize: s(18),
          }}
        >
          {d.remix} →
        </div>
      </div>
    </div>
  );
};

export const Dashboard: React.FC<{copy: Copy; width: number; height: number}> = ({
  copy,
  width,
  height,
}) => {
  const {px, stacked} = useLayout();
  const d = copy.dashboard;

  const showSidebar = !stacked;
  const sidebarWidth = showSidebar ? px(300) : 0;
  const pad = px(stacked ? 34 : 44);
  const mainWidth = width - sidebarWidth - pad * 2;
  const visibleCards = stacked ? 2 : 3;
  const gap = px(24);
  const cardWidth = (mainWidth - gap * (visibleCards - 1)) / visibleCards;
  const navSize = 21;

  return (
    <div
      style={{
        width,
        height,
        display: 'flex',
        backgroundColor: palette.surface,
        borderRadius: px(radius.card * 1.4),
        border: `${Math.max(1, px(1))}px solid ${palette.line}`,
        overflow: 'hidden',
        boxShadow: `0 ${px(40)}px ${px(110)}px ${alpha.cardShadowLift}`,
      }}
    >
      {showSidebar ? (
        <div
          style={{
            width: sidebarWidth,
            flex: '0 0 auto',
            backgroundColor: palette.ivory,
            borderRight: `${Math.max(1, px(1))}px solid ${palette.line}`,
            padding: px(28),
            display: 'flex',
            flexDirection: 'column',
            gap: px(10),
          }}
        >
          <div style={{display: 'flex', alignItems: 'center', gap: px(12), paddingLeft: px(8)}}>
            <Mark size={px(30)} color={palette.signature} />
            <span
              style={{
                fontFamily: families.display,
                fontSize: px(34),
                letterSpacing: typeScale.displayTracking,
                color: palette.ink,
              }}
            >
              {d.nav.personal}
            </span>
          </div>

          <div style={{marginTop: px(30), paddingLeft: px(14)}}>
            <SectionLabel size={13}>{d.nav.discover}</SectionLabel>
          </div>
          <SidebarItem
            label={d.nav.forYou}
            size={navSize}
            active
            icon={<SparkIcon size={px(navSize / NAV_ICON) * NAV_ICON * 1.3} color={palette.signature} />}
          />
          <SidebarItem
            label={d.nav.bookmark}
            size={navSize}
            icon={<BookmarkIcon size={px(navSize * 1.3)} color={palette.muted} />}
          />

          <div style={{marginTop: px(26), paddingLeft: px(14)}}>
            <SectionLabel size={13}>{d.nav.studio}</SectionLabel>
          </div>
          <SidebarItem
            label={d.nav.create}
            size={navSize}
            icon={<PenIcon size={px(navSize * 1.3)} color={palette.muted} />}
          />
          <SidebarItem
            label={d.nav.drafts}
            size={navSize}
            icon={<DocIcon size={px(navSize * 1.3)} color={palette.muted} />}
          />
          <SidebarItem
            label={d.nav.moments}
            size={navSize}
            icon={<NoteIcon size={px(navSize * 1.3)} color={palette.muted} />}
          />
          <SidebarItem
            label={d.nav.personal}
            size={navSize}
            icon={<PersonIcon size={px(navSize * 1.3)} color={palette.muted} />}
          />

          <div style={{marginTop: 'auto', display: 'flex', alignItems: 'center', gap: px(12)}}>
            <div
              style={{
                width: px(40),
                height: px(40),
                borderRadius: '50%',
                backgroundColor: palette.ink,
                color: palette.ivory,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                fontFamily: families.body,
                fontWeight: 600,
                fontSize: px(15),
              }}
            >
              mc
            </div>
            <span
              style={{
                fontFamily: families.body,
                fontWeight: 500,
                fontSize: px(17),
                color: palette.ink,
              }}
            >
              {d.account}
            </span>
          </div>
        </div>
      ) : null}

      <div style={{flex: '1 1 0', padding: pad, minWidth: 0}}>
        <div style={{display: 'flex', alignItems: 'center'}}>
          <div
            style={{
              fontFamily: families.display,
              fontSize: px(stacked ? 52 : 46),
              letterSpacing: typeScale.displayTracking,
              color: palette.ink,
            }}
          >
            {d.title}
          </div>
          <div style={{marginLeft: 'auto', display: 'flex', alignItems: 'center', gap: px(12)}}>
            <RoundButton size={44} background={palette.surface} border>
              <GearIcon size={px(21)} color={palette.muted} />
            </RoundButton>
            <RoundButton size={44} background={palette.ink}>
              <span
                style={{
                  fontFamily: families.body,
                  fontWeight: 600,
                  fontSize: px(15),
                  color: palette.ivory,
                }}
              >
                mc
              </span>
            </RoundButton>
            <RoundButton size={44} background={palette.signature}>
              <PlusIcon size={px(21)} color={palette.surface} />
            </RoundButton>
          </div>
        </div>

        <div style={{display: 'flex', justifyContent: 'flex-end', marginTop: px(20)}}>
          <div
            style={{
              display: 'flex',
              alignItems: 'center',
              gap: px(9),
              padding: `${px(10)}px ${px(18)}px`,
              borderRadius: px(radius.chip),
              border: `${Math.max(1, px(1))}px solid ${palette.line}`,
              fontFamily: families.body,
              fontWeight: 500,
              fontSize: px(17),
              color: palette.ink,
            }}
          >
            <SparkIcon size={px(18)} color={palette.muted} />
            {d.refresh}
          </div>
        </div>

        <div
          style={{
            height: Math.max(1, px(1)),
            backgroundColor: palette.line,
            marginTop: px(18),
            marginBottom: px(26),
          }}
        />

        <div style={{display: 'flex', gap}}>
          {d.posts.slice(0, visibleCards).map((post, index) => (
            <FeedCard key={post.handle} post={post} copy={copy} index={index} width={cardWidth} />
          ))}
        </div>
      </div>
    </div>
  );
};
