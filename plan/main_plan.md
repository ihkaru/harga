
# Development Plan: DSS TPID v2.0 - "Analis Kontekstual"

This document outlines the development plan to align the application with the specifications in `spec/main_spec.md`.

## Phase 1: Backend & AI Core Strengthening (Highest Priority)

**Objective:** Build the foundation for intelligent analysis.

1.  **Modify `TpidReportService`:**
    *   Implement `buildPrompt()` v2.0 with the enhanced knowledge base.
    *   Integrate the 4-layer analysis framework, institutional context, and intervention effectiveness catalog into the prompt.
2.  **Define and Test New JSON Schema:**
    *   Update the data structure to match the v2.0 JSON schema specified in the spec.
    *   Iterate on the prompt until the LLM consistently produces the desired output structure and quality.
3.  **Create Feedback Mechanism:**
    *   Create a new database migration and model for `analysis_feedbacks`.
    *   Create the API endpoint `POST /api/analysis-feedback` to store user feedback.

## Phase 2: Frontend Redesign (UI/UX)

**Objective:** Translate the rich JSON output into an intuitive and useful user interface.

1.  **Implement "Analis Summary" Card:**
    *   Redesign the main analysis card to display the new, richer data.
    *   Use color-coding and iconography to represent price conditions.
2.  **Build "Stakeholder Considerations" Component:**
    *   Create a tabbed or accordion component to display stakeholder-specific points.
3.  **Design "Analysis Transparency" Box:**
    *   Implement the information box to show analysis limitations and suggestions.
4.  **Connect Feedback Buttons:**
    *   Wire up the frontend feedback buttons to the API endpoint created in Phase 1.

## Phase 3: Advanced Features & Iteration (Future)

**Objective:** Extend the system's capabilities based on the new foundation.

*   **Cross-Commodity Analysis:** Identify price correlations between commodities.
*   **External Data Integration:** Incorporate data from weather or news APIs.
*   **Feedback Dashboard:** Create an admin page to review user feedback.
