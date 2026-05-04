# TalentFlow Functional Audit (Moderation, Messaging, Forum, APIs, Validation)

Generated on: 2026-04-20
Scope focus: moderation, messaging, forum posts, related business logic, APIs, media/AI/call features, and input controls.

## 1) Scope and architecture

This document is based on verified code paths in:
- src/Controller
- src/Service
- src/Form
- src/Entity
- templates/post
- templates/message
- templates/call

Main functional domains covered:
- Forum posts and comments (CRUD + realtime AJAX UX)
- Moderation/reporting workflows
- Private messaging and call events
- Video/audio calls (WebRTC + PeerJS signaling)
- Media input (image upload + voice memo recording)
- AI summary of posts
- Input validation, anti-abuse controls, and security checks

## 2) Forum and moderation: full feature map

### 2.1 Post CRUD (forum)

Primary backend: src/Controller/PostController.php

- Create post (AJAX): POST /post/create-ajax
  - Accepts: title, content, imagePath, audioPath, author (admin only)
  - Validates with ContentValidator
  - Persists Post with optional image/audio references
  - Returns serialized post JSON

- Read/list posts: GET /post/
  - Loads ordered feed
  - Computes sidebar stats (users, posts, comments, votes)
  - Detects language per post (LanguageDetector)

- Search/filter posts: GET /post/search
  - Query params: q, sort, author
  - Returns JSON list for client-side rerender

- Post details: GET /post/{id}/detail
  - Returns post payload + comments JSON
  - Enforces hidden-post visibility logic

- Edit post (AJAX): POST /post/edit-ajax/{id}
  - Author or admin only
  - Revalidates title/content/media

- Delete post: POST /post/{id}/delete
  - Author or admin only
  - CSRF token required

### 2.2 Comment CRUD

Also in src/Controller/PostController.php

- Create comment: POST /post/{id}/comment-ajax
  - Content validation via ContentValidator
  - Optional admin author override

- Edit comment: POST /post/comment/{id}/edit-ajax
  - Author or admin only
  - Validation enforced

- Delete comment: POST /post/comment/{id}/delete-ajax
  - Author or admin only
  - CSRF token required

### 2.3 Voting

- Upvote: POST /post/{id}/upvote
- Downvote: POST /post/{id}/downvote

Behavior:
- Toggle same vote removes vote
- Switching vote applies +2/-2 compensation to upvote counter
- One vote per (user, post) enforced by DB unique constraint in Vote entity

### 2.4 Moderation and reports

Primary backend: src/Controller/PostReportController.php

- Report a post: POST /post/{id}/report
  - User must be authenticated and non-admin
  - Blocks duplicate pending reports by same user/post
  - Valid reasons: Spam, Inappropriate, Misinformation, Other
  - Optional description max 1000 chars

- Admin moderation panel: GET /admin/reports

- Dismiss report: POST /admin/reports/{id}/dismiss

- Hide post from report: POST /admin/reports/{id}/hide-post
  - Sets post.hidden=true
  - Resolves pending reports for that post

- Unhide post: POST /admin/reports/post/{id}/unhide

Entity state model:
- PostReport status: pending, dismissed, resolved

## 3) Messaging and calls

### 3.1 Messaging CRUD and conversation operations

Primary backend: src/Controller/MessageController.php

- List conversations: GET /message/
- Open conversation: GET /message/conversation/{id}
  - Access check: conversation must involve current user
  - Marks conversation as read

- Send message: POST /message/send
  - Input: conversation_id, content
  - Validation: content length 1..2000

- Start conversation: GET /message/start/{id}
  - Creates conversation if not existing

- Fetch messages (polling API): GET /message/fetch/{id}
  - Returns message list JSON with computed canEdit flag

- Edit message: POST /message/edit/{id}
  - Sender only
  - Edit window limited to 15 minutes

- Delete message: POST /message/delete/{id}
  - Sender only
  - Delete window limited to 15 minutes

- Unread count: GET /message/unread-count

Message type model (src/Entity/Message.php):
- text
- call_ended
- call_missed

### 3.2 Video/audio call flow

Primary backend: src/Controller/CallController.php
Primary frontend: templates/call/room.html.twig

Endpoints:
- GET /call/room/{id}
- POST /call/signal/{id}
- GET /call/check/{id}
- POST /call/end/{id}

How signaling works:
- Uses temporary server-side JSON file in system temp directory
- Each participant registers peerId and mode
- Other side polls /call/check to discover remote peer
- Lower user ID initiates PeerJS call to avoid duel-call race

Call completion logging:
- /call/end writes a Message entity with type call_ended/call_missed
- Message content JSON payload includes:
  - mode: video|audio
  - duration: seconds

Client capabilities in call UI:
- Mic toggle
- Camera toggle (including adding video track mid-call)
- Screen share toggle via getDisplayMedia
- Connection overlays, error handling, timer

## 4) Advanced business logic (metier)

### 4.1 Content moderation intelligence

Service: src/Service/ContentValidator.php

Rules implemented:
- Bad word detection (FR + EN lexicon)
- Leet-style normalization before matching
- Spam/repetition heuristics:
  - repeated chars
  - repeated short words
  - low-entropy repeated text

Validation methods:
- validatePostTitle
- validatePostContent
- validateComment

### 4.2 Language detection for forum

Service: src/Service/LanguageDetector.php
- Detects language of post title/content and exposes badges in feed

### 4.3 Candidate matching score (recruitment metier)

Service: src/Service/CandidatureMatchingService.php

Score model:
- Token overlap score (70%)
- String similarity score (30%)
- Final bounded score in [0..100]

### 4.4 Workflow state machine for candidatures

Service: src/Service/CandidatureWorkflowService.php
- Uses state_machine.candidature_process
- Applies allowed transitions only
- Persists status history with actor/note
- Sends non-blocking status notification emails

### 4.5 Decision automation (final decision)

Controller: src/Controller/DecisionFinaleController.php
- Auto-sync decisions from realized interviews
- Auto-decide based on score thresholds
- API endpoint to fetch interview score:
  - GET /decisions-finales/api/entretien/{id}/score

## 5) API inventory (internal + external)

### 5.1 Internal APIs used by your features

Forum and moderation:
- GET /post/search
- POST /post/create-ajax
- GET /post/{id}/detail
- POST /post/edit-ajax/{id}
- POST /post/{id}/delete
- POST /post/{id}/comment-ajax
- POST /post/comment/{id}/edit-ajax
- POST /post/comment/{id}/delete-ajax
- POST /post/{id}/upvote
- POST /post/{id}/downvote
- POST /post/{id}/summarize
- POST /post/{id}/report
- GET /admin/reports
- POST /admin/reports/{id}/dismiss
- POST /admin/reports/{id}/hide-post
- POST /admin/reports/post/{id}/unhide

Messaging and calls:
- GET /message/
- GET /message/conversation/{id}
- POST /message/send
- GET /message/start/{id}
- GET /message/fetch/{id}
- POST /message/edit/{id}
- POST /message/delete/{id}
- GET /message/unread-count
- GET /call/room/{id}
- POST /call/signal/{id}
- GET /call/check/{id}
- POST /call/end/{id}

Chatbot:
- POST /chatbot/message

Recruitment-related connected flows:
- GET/POST /candidature/new
- GET /candidature/search
- POST /candidature/{id}/status-transition/{transition}
- GET/POST /entretiens/new
- GET /entretiens/search
- GET /decisions-finales/api/entretien/{id}/score

### 5.2 External APIs/services used

AI and translation/media:
- Groq chat completions API
  - URL: https://api.groq.com/openai/v1/chat/completions
  - Used by: post summarize endpoint
  - Auth: Bearer GROQ_API_KEY

- ImgBB upload API
  - URL pattern: https://api.imgbb.com/1/upload?key=...
  - Used by frontend to upload post images
  - Output URL stored as Post.imagePath

- MyMemory translation API
  - URL: https://api.mymemory.translated.net/get
  - Used by forum frontend language translation helper

- PeerJS cloud signaling/bootstrap (library CDN)
  - URL: https://unpkg.com/peerjs@1.5.4/dist/peerjs.min.js

Other integrated services in project:
- Daily.co REST API (room/token management service)
- HaveIBeenPwned password range API (k-anonymity)

## 6) Browser APIs used in your features

In forum and call templates:
- navigator.mediaDevices.getUserMedia
  - Audio memo recording in forum
  - Camera/mic capture for calls

- MediaRecorder
  - Records voice memo as audio/webm
  - Converts recording to Data URL for post payload

- navigator.mediaDevices.getDisplayMedia
  - Screen sharing in calls

- Web Audio API (AudioContext)
  - Notification/chime sounds in messaging UI

- SpeechSynthesis API
  - Text-to-speech readout for post content

- Fetch API
  - Used throughout all AJAX flows

## 7) Input controls and validation

### 7.1 Forum input controls

Post title/content:
- Title min 3, max 255 (ContentValidator + PostType attrs + entity constraints)
- Content max 5000 in ContentValidator for AJAX flow
- Content min 10 if no image
- Bad word and spam/repetition checks

Comment content:
- Min 2, max 2000 in ContentValidator for AJAX flow
- Anti-spam + bad word checks

Report form:
- reason must be in allowed enum values
- description max 1000

Image upload (frontend):
- Client-side size check: max 10 MB
- Upload to ImgBB

Voice memo:
- Browser permission required for microphone
- Stored as Data URL string in JSON payload

### 7.2 Messaging controls

- Message content length 1..2000
- Edit/delete allowed only for sender and only within 15 minutes
- Conversation ownership checks on fetch/open/send

### 7.3 Recruitment form controls (related metier)

From CandidatureType:
- Many fields with UI limits and constraints
- CV/letter file constraints:
  - max 5MB
  - MIME: PDF/DOC/DOCX

## 8) Security and authorization controls

Implemented controls observed:
- Role checks and route guards (ROLE_ADMIN, ROLE_RH, ROLE_USER, ROLE_CANDIDAT)
- Ownership checks:
  - posts/comments/message actions restricted to owner or admin where applicable
- CSRF checks for sensitive POST actions (delete, moderation actions, transitions)
- Access checks for conversations and call rooms (must involve current user)
- Hidden post visibility control (admin can still view original content)

## 9) Data model summary for your requested area

Core entities:
- Post: title, content, imagePath, audioPath, upvotes, hidden, author
- Comment: content, author, post
- Vote: user, post, type (up/down), unique per user/post
- PostReport: post, reportedBy, reason, description, status
- Conversation: userOne, userTwo, timestamps
- Message: conversation, sender, content, isRead, type

Important enum-like values:
- Vote.type: up | down
- Message.type: text | call_ended | call_missed
- PostReport.status: pending | dismissed | resolved
- PostReport.reason: Spam | Inappropriate | Misinformation | Other

## 10) End-to-end flows (what happens in practice)

### 10.1 Create post with image + audio
1. User writes title/content in modal
2. Optional image uploaded to ImgBB from frontend
3. Optional voice memo recorded with MediaRecorder and encoded to Data URL
4. Frontend sends JSON to POST /post/create-ajax
5. Backend validates (title/content/bad words/spam) and stores Post
6. Feed rerenders via returned serialized payload

### 10.2 Report moderation flow
1. User opens report modal and selects reason
2. Frontend POST /post/{id}/report
3. Backend enforces auth, dedup, reason enum, description length
4. Admin reviews in /admin/reports
5. Admin dismisses or hides post

### 10.3 Call flow from messaging
1. User starts call from conversation header
2. Call room opens and acquires media permissions
3. Each side posts peer ID via /call/signal/{id}
4. Client polls /call/check/{id}
5. Caller side opens PeerJS call
6. On hangup, frontend posts /call/end/{id}
7. Backend logs call event message (ended/missed + duration/mode)

## 11) Notes and caveats

- PostType allows up to 10000 chars in form attr, while AJAX validator enforces 5000 chars; AJAX path is stricter.
- Forum image/audio are persisted as path/string references; image upload relies on external ImgBB availability.
- Post summarize depends on GROQ_API_KEY and Groq API availability.
- Call signaling is file-based temp storage; lightweight and simple but not ideal for horizontal scaling.

## 12) Quick file index (where each feature lives)

Controllers:
- src/Controller/PostController.php
- src/Controller/PostReportController.php
- src/Controller/MessageController.php
- src/Controller/CallController.php
- src/Controller/ChatbotController.php
- src/Controller/CandidatureController.php
- src/Controller/EntretienController.php
- src/Controller/DecisionFinaleController.php

Services:
- src/Service/ContentValidator.php
- src/Service/LanguageDetector.php
- src/Service/CandidatureMatchingService.php
- src/Service/CandidatureWorkflowService.php
- src/Service/ChatbotService.php
- src/Service/DailyService.php
- src/Service/HaveIBeenPwnedService.php

Frontend templates:
- templates/post/index.html.twig
- templates/message/index.html.twig
- templates/call/room.html.twig

Forms and entities:
- src/Form/PostType.php
- src/Form/CommentType.php
- src/Form/CandidatureType.php
- src/Entity/Post.php
- src/Entity/Comment.php
- src/Entity/Vote.php
- src/Entity/PostReport.php
- src/Entity/Conversation.php
- src/Entity/Message.php
