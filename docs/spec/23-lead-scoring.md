# 23 — Lead Scoring

Automated lead scoring based on profile completeness, engagement, and recency. Helps sales reps prioritize their pipeline.

---

## Approach

**Rule-based scoring** with configurable weights stored in `config/crm.php`. Scores are computed on-demand and cached on the lead row. A scheduled command recalculates stale scores daily.

---

## Database

### `leads` — add columns

```
ALTER TABLE leads ADD COLUMN score INTEGER DEFAULT 0;
ALTER TABLE leads ADD COLUMN score_breakdown JSON NULL;
ALTER TABLE leads ADD COLUMN scored_at TIMESTAMP NULL;
```

Single migration: `add_scoring_columns_to_leads_table`.

---

## Scoring Rules

Stored in `config/crm.php` under `lead_scoring`:

```php
'lead_scoring' => [
    'weights' => [
        // Profile completeness (max 25 pts)
        'has_email'           => 5,
        'has_phone'           => 5,
        'has_organization'    => 5,
        'has_title'           => 3,
        'has_description'     => 2,
        'has_estimated_value' => 5,

        // Engagement (max 50 pts)
        'activity_count'      => 2,  // per activity, capped at 20 pts
        'activity_recency'    => 15, // full points if activity in last 7 days, decays
        'email_opened'        => 5,  // from campaign recipient tracking
        'email_clicked'       => 10, // from campaign recipient tracking

        // Pipeline position (max 25 pts)
        'status_contacted'    => 5,
        'status_qualified'    => 10,
        'status_proposal'     => 15,
        'status_negotiation'  => 20,
    ],
    'recency_decay_days' => 30,  // score decays to 0 over this many days
    'stale_after_hours'  => 24,  // recalculate scores older than this
],
```

---

## Service

### LeadScoringService

```php
class LeadScoringService
{
    public function calculate(Lead $lead): int
    {
        $breakdown = [];

        // Profile completeness
        $breakdown['has_email'] = $lead->email ? config('crm.lead_scoring.weights.has_email') : 0;
        $breakdown['has_phone'] = $lead->phone ? config('crm.lead_scoring.weights.has_phone') : 0;
        $breakdown['has_organization'] = $lead->organization_id ? config('crm.lead_scoring.weights.has_organization') : 0;
        $breakdown['has_title'] = $lead->title ? config('crm.lead_scoring.weights.has_title') : 0;
        $breakdown['has_description'] = $lead->description ? config('crm.lead_scoring.weights.has_description') : 0;
        $breakdown['has_estimated_value'] = $lead->estimated_value ? config('crm.lead_scoring.weights.has_estimated_value') : 0;

        // Engagement — activity count (capped)
        $activityCount = $lead->activities()->count();
        $perActivity = config('crm.lead_scoring.weights.activity_count');
        $breakdown['activity_count'] = min($activityCount * $perActivity, 20);

        // Engagement — recency decay
        $lastActivity = $lead->activities()->latest()->first();
        $breakdown['activity_recency'] = $this->recencyScore($lastActivity?->created_at);

        // Campaign engagement
        $breakdown['email_opened'] = $this->campaignEngagement($lead, 'opened');
        $breakdown['email_clicked'] = $this->campaignEngagement($lead, 'clicked');

        // Pipeline position
        $breakdown['pipeline_position'] = $this->pipelineScore($lead->status);

        $score = array_sum($breakdown);

        $lead->update([
            'score'           => $score,
            'score_breakdown' => $breakdown,
            'scored_at'       => now(),
        ]);

        return $score;
    }

    public function recalculateAll(): int  // returns count of recalculated leads

    public function recalculateStale(): int  // only leads where scored_at is old or null

    private function recencyScore(?Carbon $lastActivityAt): int
    private function pipelineScore(string $status): int
    private function campaignEngagement(Lead $lead, string $type): int
}
```

---

## Recency Decay Formula

```
daysAgo = now() - lastActivityAt (in days)
decayDays = config('crm.lead_scoring.recency_decay_days')
factor = max(0, 1 - (daysAgo / decayDays))
recencyScore = round(config('crm.lead_scoring.weights.activity_recency') * factor)
```

If no activity exists, recency score = 0.

---

## Automatic Recalculation

### On events

Recalculate score when:
- Lead activity is created/deleted
- Lead status changes
- Lead profile is updated (email, phone, org, etc.)

Done via `LeadScoringService::calculate()` called from the respective service methods.

### Scheduled command

```php
class RecalculateLeadScores extends Command
{
    protected $signature = 'crm:recalculate-lead-scores';
    protected $description = 'Recalculate stale lead scores';

    // Runs daily - recalculates leads where scored_at is older than stale_after_hours
}
```

**Schedule:** `$schedule->command('crm:recalculate-lead-scores')->daily();`

---

## API Endpoints

| Method | URI | Action | Description |
|---|---|---|---|
| `GET` | `/leads/{lead}/score` | `LeadController@score` | Get score + breakdown |
| `POST` | `/leads/{lead}/score/recalculate` | `LeadController@recalculateScore` | Force recalculate |

The score is also included in `LeadResource` responses:

```json
{
    "id": 1,
    "first_name": "John",
    "score": 72,
    "score_breakdown": {
        "has_email": 5,
        "has_phone": 5,
        "has_organization": 5,
        "activity_count": 14,
        "activity_recency": 15,
        "pipeline_position": 15,
        "email_opened": 5,
        "email_clicked": 10,
        "has_title": 3,
        "has_description": 0,
        "has_estimated_value": 0
    },
    "scored_at": "2026-03-08T10:00:00Z"
}
```

---

## Filter Integration

Add `score_min` and `score_max` params to `LeadFilter`:

```
GET /api/leads?score_min=50&score_max=90
GET /api/leads?sort=score&direction=desc
```

---

## Permissions

No additional permissions — score is visible to anyone who can view the lead. Recalculate follows `update` policy on the lead.
