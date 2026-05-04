# Wiki Schema — Research Wiki for Tomato Instance Segmentation PhD

**Author:** BEKHOUCHE Mouadh | PhD | Annaba, Algeria | 2026
**Thesis framing:** Data-efficient tomato perception under annotation scarcity

---

## Purpose

This wiki is a persistent, LLM-maintained knowledge base that sits between
raw academic papers and the thesis writing process.

The human (Mouadh) sources papers and asks questions.
The LLM agent reads, synthesizes, cross-references, and writes.
The wiki grows richer with every paper ingested.

The end product of this wiki is not the wiki itself —
it is the Related Work section and thesis argument, pre-assembled.

---

## Domain Context

The thesis studies tomato instance segmentation using deep learning,
specifically in greenhouse and agricultural settings.

Core argument:
  Most existing papers train on small, fully-labeled custom datasets
  and claim architectural novelty. None seriously address what happens
  when pixel-level masks are scarce. TomatoMAP-Seg is the first
  structured dataset where annotation scarcity can be studied rigorously.
  Paper 1 provides the experimental evidence for this claim.

Dataset situation:
  - TomatoMAP-Seg: 3,616 total segmentation images
  - Currently available labels: ~700
  - Unlabeled pool: ~2,400+ images (no masks yet)
  - 10 fine-grained classes (nascent, mini, unripe green, semi-ripe,
    fully ripe, sizes 2mm–12mm)

Baseline result (frozen, do not change):
  - Model: Detectron2 Mask R-CNN
  - Segm AP: 8.2% | Bbox AP: 2.8% | fg_cls_accuracy: ~49%
  - Interpretation: model collapses on rare/small classes due to
    annotation scarcity — this IS the thesis motivation

---

## Directory Structure

docs/wiki/
├── SCHEMA.md          ← this file (LLM reads every session)
├── index.md           ← full catalog of every page
├── log.md             ← append-only timeline of all sessions
├── papers/            ← one page per paper
├── concepts/          ← synthesized topic pages
└── synthesis/         ← cross-cutting analysis and thesis argument

---

## Paper Page Format

File: docs/wiki/papers/{slug}.md
Slug convention: {topic}-{year}  e.g. yolov8-tomato-seg-2024

---
title: ""
year:
authors: []
doi: ""
source: ""         # openalex / arxiv / crossref / manual
method: ""         # model architecture used
dataset: ""        # dataset(s) used in the paper
metric: ""         # primary reported metric (mask mAP preferred)
thesis_relevance: ""   # ⭐ low / ⭐⭐ medium / ⭐⭐⭐ high
---

## Summary
(2–3 sentences: what problem, what method, what result)

## Method
(architecture, training setup, key design choices)

## Dataset and Results
(dataset name, size, label availability, reported numbers)

## Thesis Connection
> How does this paper support or challenge the thesis argument?
> Does it address annotation scarcity? If not, say so explicitly.

## Contradictions and Gaps
> What does this paper NOT do that your paper does?
> Is the dataset fully labeled? Controlled conditions only?
> No field condition evaluation? → flag this

---

## Concept Page Format

File: docs/wiki/concepts/{topic}.md
Topic convention: lowercase-hyphenated, no year

## Definition
(what this concept is, 2–3 sentences)

## Key Papers
| Paper | Year | Method | Dataset | Metric | Notes |
|-------|------|--------|---------|--------|-------|

## Synthesis
(what the papers agree on, where they diverge)

## Contradictions
(explicit conflicts between papers — link both pages)

## Thesis Relevance
(how this concept feeds into the thesis argument)

---

## Priority Concept Pages

Build these first, in this order:

1. concepts/annotation-scarcity-agricultural-vision.md
   → The core justification for your paper's existence

2. concepts/semi-supervised-instance-segmentation.md
   → The method space: pseudo-labeling, Mean Teacher, teacher-student

3. concepts/tomatomap-dataset.md
   → Your dataset: structure, subsets, acquisition design, label situation

4. concepts/plantvillage-limitations.md
   → Why the standard benchmark fails in real conditions
   → This is the "prior work is insufficient" argument

5. concepts/yolov8-seg-tomato.md
   → Accuracy comparison table of all YOLOv8-seg tomato papers
   → Becomes the Related Work comparison table directly

6. concepts/mask-rcnn-tomato.md
   → Same as above but for Mask R-CNN family

7. concepts/pseudo-labeling.md
   → Specific SSL technique likely used in Paper 1 experiments

---

## Synthesis Pages

File: docs/wiki/synthesis/{name}.md

### synthesis/gaps-and-contradictions.md
The most important file in the wiki.
Every time a paper is ingested:
- If it trains on a fully-labeled dataset without addressing scarcity
  → add one row to the gaps table
- If it evaluates only in controlled/lab conditions
  → add to the "field condition gap" section
- If it contradicts another paper's result
  → add a contradiction entry with links to both papers

Format:

## Annotation Scarcity Gap
| Paper | Dataset | Labels | Addresses Scarcity? | Notes |
|-------|---------|--------|---------------------|-------|

## Field Condition Gap
| Paper | Setting | Real Field? | Notes |
|-------|---------|-------------|-------|

## Contradictions
| Claim | Paper A | Paper B | Resolution |
|-------|---------|---------|------------|

### synthesis/paper1-related-work.md
The draft Related Work section for Paper 1.
Assembled from concept pages — not written from scratch.
Structure:
  2.1 Tomato Instance Segmentation
  2.2 Semi-Supervised Instance Segmentation
  2.3 Annotation Scarcity in Agricultural Vision
  2.4 The TomatoMAP Dataset

### synthesis/baseline-evidence.md
Contextualizes the 8.2% Detectron2 result against other papers.
Answers: is 8.2% expected given 700 labels across 10 classes?
Builds the argument for why semi-supervised learning is necessary.

---

## Ingest Workflow (follow every session)

When ingesting a new paper:

1. Create docs/wiki/papers/{slug}.md from the paper page template
2. Fill all frontmatter fields from the paper metadata
3. Write Summary, Method, Dataset and Results sections
4. Write Thesis Connection — explicitly state whether this paper
   addresses annotation scarcity or not
5. Write Contradictions and Gaps section
6. Update ALL relevant concept pages:
   - Add a row to the accuracy table if applicable
   - Note any contradiction with existing entries
7. Update synthesis/gaps-and-contradictions.md if applicable
8. Add one line to index.md:
   [[papers/{slug}]] | {year} | {method} | {thesis_relevance}
9. Append one line to log.md:
   ## [{date}] ingest | {title}

---

## Query Operation

When asked a question against the wiki:

1. Read index.md to find relevant pages
2. Read those pages in full
3. Synthesize an answer with inline citations to wiki pages
4. If the answer is valuable → save it as a new synthesis page
5. Append to log.md:
   ## [{date}] query | {question summary}

---

## Lint Operation (run monthly)

Check for:
- papers/ pages where thesis_relevance is empty → needs analysis
- Concept pages with fewer than 3 papers in their table → needs more reading
- synthesis/gaps-and-contradictions.md rows with empty Notes → needs follow-up
- index.md entries missing for existing pages → sync index
- Orphan pages: exist in papers/ but no concept page links them

---

## The Core Thesis Argument (never lose this)

"Most tomato segmentation papers train on small, fully-labeled custom
datasets and claim architectural improvements. The annotation cost is
treated as a solved problem. It is not. TomatoMAP-Seg makes the
annotation scarcity problem structurally visible: 3,616 images exist,
but only ~700 have masks. This paper is the first to treat that gap
as the research question rather than an inconvenience to work around."

This argument must be traceable through:
- synthesis/gaps-and-contradictions.md  (evidence from literature)
- synthesis/baseline-evidence.md        (evidence from your own data)
- synthesis/paper1-related-work.md      (the assembled argument)
