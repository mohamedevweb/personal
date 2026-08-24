# Personal — X launch playbook

Working document for the launch of Personal on X. Every asset below is written
against the copy that already exists in the product, so nothing here introduces
a claim the landing page does not already make.

**Source of the framework.** Juan (@0xfJuan) — *How To Get Your Launch Trending
On X*, four stages: research → hook → attention → conversion. The tweet is a
link to an X Article whose body is gated, so what follows is built on the
framework's structure, not on his specific numbers. His piece closes with a
consultation CTA for his agency; the framework is sound, the specifics are the
paid part.

---

## 0. Asset inventory

What already exists, and what state it is in.

| Asset | Where | State |
|---|---|---|
| Launch film, EN + FR | `remotion/src/LaunchFilm.tsx` | Built. ~34s at 30fps (1031 frames) |
| Scripted product demo | `docs/marketing/record-demo.mjs` → `personal-demo.webm` | Built. 7 beats, real app, synthesized cursor |
| Product screenshots, desktop + mobile | `docs/marketing/screenshots/` | 14 shots |
| Landing page | `frontend/app/pages/index.vue` | Live at usepersonal.app |
| Waitlist / early access | Landing CTA | Live, free during early access |
| Blog | `frontend/app/pages/blog.vue` | Live |
| **Story page** | `frontend/app/pages/story.vue` | **Empty — all three chapters say "To be written"** |

**Blocker.** Two of the hook variants below, and the whole T-1 warm-up post,
send people to `/story`. Right now that page has three headings and no body.
Either write it before T-7 or cut those variants. Do not launch traffic into an
empty page.

---

## 1. Research (T-14)

The goal of this stage is one sentence: *what shape does a winning post have in
our niche this week.* Not "what should we say" — what **form** wins.

**We can run this with our own pipeline.** `InstagramDataProvider` already
abstracts ScrapeCreators and Apify, and both have X/Twitter endpoints. Pointing
the existing outlier maths at ~50 X accounts is a driver, not a new system. The
ratio logic — a post beating *its own account's* average — is exactly the right
metric here too, and it is more honest than raw view counts.

Account list to sample (50, roughly even across three groups):

- AI/creator tooling founders launching in the last 6 months
- Build-in-public founders at 10K–150K followers
- Creator-economy commentators (they are the ones who quote-tweet)

What to extract, per post: format (video / thread / single image + claim),
length of the first line, whether the link is in the root post or a reply,
whether a comment-gate was used, and time of day. That is the pattern. Feed the
sample to Claude and ask for the *structural* commonalities, not the topics —
same rule the product's own prompts enforce: borrow structure, never subject
matter.

---

## 2. The hook post

### Rules for the root post

- No link in the root post. It goes in your own first reply, pinned.
- No hashtags.
- First line has to work as the only line someone reads.
- Video native-uploaded, never a YouTube link.
- The comment-gate word should be one word, all caps, easy to type: **PERSONAL**.

### Eight hook variants

Ranked. #1 is the recommendation.

---

**H1 — The mechanism (recommended)**

> Every AI writing tool produces posts that could have been written by anyone.
>
> I spent the last year building the opposite.
>
> Personal reads your last 40 Instagram posts, finds the ones outperforming in
> your niche this week, and rewrites them with something that actually happened
> to you.
>
> Content only you could post.
>
> Comment PERSONAL and I'll send you access.

*Why it works:* it states the enemy, the mechanism, and the payoff in three
beats, and the mechanism is genuinely unusual. This is the variant that survives
being screenshotted without the video.

---

**H2 — The enemy**

> "AI content" is the most boring phrase in tech.
>
> Not because the model can't write. Because it has nothing to write about.
>
> So I gave it something to write about: your actual week.

*Why it works:* highest quote-tweet potential — it hands people an opinion to
agree or argue with. Highest risk too: it invites "another AI slop tool"
replies. Only run this one if you are ready to reply for four straight hours.

---

**H3 — The number**

> 8.4×.
>
> Not the biggest post on Instagram. The post beating the account that published
> it — in your niche, at your size, while it's still climbing.
>
> That's the only number a creator should be copying from. So I built the tool
> that finds it every morning.

*Why it works:* the 8.4× is already the landing page's proof block, so the site
confirms the post instead of repeating it. Concrete numbers stop the scroll.

---

**H4 — The refusal**

> Personal will never post for you.
>
> Not a missing feature. The whole point.
>
> It finds what's working in your niche, connects it to something that actually
> happened to you this week, and hands you a draft. You decide if it's you.

*Why it works:* trust-first, and it pre-empts the biggest objection in the
category. Lower ceiling, higher conversion-per-view. Good as the T+1 follow-up
if H1 goes wide.

---

**H5 — The founder story** *(requires `/story` to be written)*

> I stopped posting for eight months because it started feeling like a second
> job.
>
> I'm a builder. I had things worth saying and no system for saying them.
>
> So I built the system. It's called Personal.

*Why it works:* the most human of the eight, and the most in your voice. Weakest
mechanism clarity — pair it with the video doing the explaining.

---

**H6 — Demo-first**

> No pitch. Just watch it work.
>
> Instagram connected → 40 posts read → what it understood about me, in writing
> → a draft in my voice.
>
> One take, no cuts.

*Why it works:* lowest-friction, highest credibility, works even if the copy is
weak. Requires the video to be genuinely uncut — do not claim "no cuts" over an
edited film. Use `record-demo.mjs` output, not the Remotion film.

---

**H7 — The category line**

> Claude for personal branding.
>
> That's the whole pitch. Here's the product.

*Why it works:* your existing cold-open line, and category-borrowing hooks
travel fast in this niche. *Risk:* it leans on Anthropic's brand for
comprehension. Fine as a line in a thread, thin as a standalone launch post.

---

**H8 — The insight**

> The best post in your niche this week isn't the one with the most views.
>
> It's the one that did 8× what its own account normally does — because that's
> the post where the *format* did the work, not the follower count.
>
> Those are the only posts worth learning from. Personal finds them every
> morning.

*Why it works:* teaches something true and useful before it sells anything. The
strongest variant for earning follows from people who won't sign up today.

---

## 3. The X cut of the launch film

**The existing film is not the launch video.** It runs ~34s, it opens on 44
frames (1.5s) of particle atmosphere before the category line, and the claim
doesn't land until roughly frame 92 (~3.1s). In an X feed, 3.1 seconds of
motion design before the first idea is the whole battle lost. It is also entirely
motion-design — there is not one frame of the real app in it.

Cut a separate 60–75s X version. Different object, same brand.

### Shot list

| Time | Shot | Source | Audio |
|---|---|---|---|
| 0:00–0:03 | Hard claim on a still frame: **"Content only you could post."** No logo, no animation, no cold open. | New card, `Claim.tsx` styling | Silence, or one hit |
| 0:03–0:09 | Real screen recording: Instagram connect → "Reading 12 of 40" | `record-demo.mjs` beat 1–2 | VO starts |
| 0:09–0:20 | The read-back card: Niche / Audience / Tone / Positioning filling in line by line | `record-demo.mjs` beat 6 (`/personal`) | "It tells you what it understood, in writing." |
| 0:20–0:34 | The feed. The 8.4× card lights. Hold on the ratio badge. | `record-demo.mjs` beat 2–3 | "Not the biggest posts. The ones beating the account that published them." |
| 0:34–0:44 | Moments: a real note going in | `record-demo.mjs` beat 5 | "And then it asks what actually happened to you." |
| 0:44–1:00 | Remix: the draft assembling — hook, three beats, caption | `record-demo.mjs` beat 4 | "Your structure. Your voice. Your story." |
| 1:00–1:06 | Stamp: **"A draft, never a post."** | `Write.tsx` stamp | Beat of silence |
| 1:06–1:12 | End card: wordmark, "Content only you could post.", usepersonal.app | `EndCard.tsx` | Out |

**Specs.** 1080×1080 or 1080×1350 — square/portrait beats 16:9 in-feed. Burn in
captions; most of the feed is muted. yuv420p, even dimensions (the encode block
in `record-demo.mjs` already does this). Under 2:20 total or X re-encodes hard.

**Keep the 34s film** for the landing page hero, where someone has already
chosen to watch. It is the right length there.

---

## 4. Pinned reply

Post this as your own first reply, within 30 seconds of the root post, then pin
it to your profile.

> Personal is free during early access — I'm onboarding every creator by hand
> right now, so it's a small group.
>
> usepersonal.app
>
> Built solo over the last year. Ask me anything about how it works, I'm here
> all day.

Second reply, ~20 minutes later, once replies are flowing — this is the one that
converts skeptics:

> To answer the question I'm getting most: it never posts for you, and your
> Moments are never shown to another creator. It drafts, you decide, you can
> delete anything. That constraint is the product.

---

## 5. Quote-tweet copy for supporters

Send each person **one** variant, assigned by you — never a menu, and never the
same text to two people. Identical quote-tweets read as coordination and get
suppressed. Tell them the window, not the minute.

### Tier A — large accounts (100K+), 2–3 people

Spread across the first 90 minutes. These are endorsements, so they should sound
like judgement, not enthusiasm.

> A1: The interesting bet here isn't the writing. It's that it starts from
> outlier posts measured against their own account's baseline. That's a much
> better signal than raw views and almost nobody does it.

> A2: Most tools in this category generate. This one starts from two things that
> already exist — what's working in your niche, and what happened to you. Worth
> a look.

> A3: Been watching @your-handle build this one in public for months. Shipped, and
> it's good.

### Tier B — mid accounts (10K–100K), 6–10 people

Minutes 30–180, staggered. These are the workhorses. They should sound like
practitioners.

> B1: The "it tells you what it understood about you before it writes anything"
> step is the part I haven't seen elsewhere. That's the trust problem solved in
> one screen.

> B2: Finally an AI content tool whose answer to "does it post for me" is "no,
> never." Correct answer.

> B3: 8.4× against the account's own average is a genuinely different way to
> pick what to learn from. Most "viral post" tools just show you big accounts.

> B4: If you've ever stared at a blank caption box on a Sunday night, this is
> for you.

> B5: The Moments idea — it asks what actually happened to you this week, then
> writes from that — is the bit that makes the output not sound like everything
> else.

> B6: Solo-built, shipped, free while he onboards by hand. Go look.

### Tier C — friends, early users, small accounts, 10–20 people

Hours 2–6, and into T+1. These should be short, specific and unpolished. One
concrete detail each. Ask them for their own sentence rather than sending copy —
and if they want a starting point:

> C1: Used this for a week. The drafts actually sound like me, which I did not
> expect.

> C2: signed up, it read my last 40 posts and got my tone right on the first try

> C3: this is the first one of these I haven't immediately closed

**What we don't do:** no engagement pods, no bought accounts, no fabricated
usage numbers, no "10,000 creators already" when it's not true. The product's
entire pitch is that it doesn't fake your voice. Faking the launch would be the
one inconsistency people never forgive.

---

## 6. Comment-gate DM

Send manually. Batch every 30–45 minutes on launch day. Keep it short — long DMs
read as automated.

> Hey — thanks for commenting.
>
> Here's early access: usepersonal.app
>
> It's free right now and I'm setting up accounts by hand, so if anything is
> broken or confusing just reply here and it's me who answers.
>
> — Mohamed

For anyone whose comment was a real question, answer the question first, link
second. Those are the ones who become users.

**Practical note.** X rate-limits DMs, and non-followers may have DMs closed. For
those, reply in-thread with "sent you a DM — if it didn't land, it's
usepersonal.app". Budget the DM sending as real work: 200 comments is about two
hours.

---

## 7. Run of show

| When | What |
|---|---|
| T-14 | Research pass over 50 accounts. Lock the hook variant. Start the supporter list. |
| T-10 | **Write `/story`.** Three chapters, currently empty. |
| T-7 | Cut the 60–75s X video. Write all quote-tweet variants. |
| T-5 | Confirm supporters individually. Send each their assigned copy + their window. |
| T-3 | Dry-run the DM flow. Check the waitlist actually handles a spike. |
| T-1 | Warm-up post: the "why I built this" piece, linking `/story`. No product pitch. |
| T-0, 09:00–11:00 ET | Root post + video. Pinned reply within 30s. |
| T-0, +0 to +4h | Reply to everything. Nothing else in the calendar. Tier A and B firing on schedule. |
| T-0, +30min | Second pinned reply (the "never posts for you" one). |
| T+1 | Follow-up post in the same thread — H4 or H8, whichever wasn't the root. DM the overnight comments. |
| T+2 | French cut. The FR film already exists in `copy.ts`; the French startup scene is a separate audience and a second bite. |
| T+3 | The honest numbers post: what the launch actually did. This one always outperforms and it costs nothing but candour. |

---

## 8. What to measure

Reach is the vanity number; the launch is a funnel and every stage is countable.

- Impressions on the root post
- Comment-gate replies (the real engagement signal, and the lead list)
- DMs sent → link clicks
- Waitlist signups
- **Instagram accounts actually connected** — the only number that means anything

Write the T+3 post from these, whatever they say.
