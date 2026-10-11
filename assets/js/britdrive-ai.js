document.addEventListener("DOMContentLoaded", function () {
  "use strict";

  /* =========================================================
       BRITDRIVE AI
       Interactive Floating Workspace
       ========================================================= */

  const overlay = document.getElementById("britdriveAiOverlay");

  const form = document.getElementById("britdriveAiForm");

  const input = document.getElementById("britdriveAiInput");

  const actionButton = document.getElementById("britdriveAiAction");

  const statusLine = document.getElementById("britdriveAiStatusLine");

  if (!overlay || !form || !input || !actionButton) {
    return;
  }

  /* =========================================================
       CONFIGURATION
       ========================================================= */

  const API_URL = "/vms/api/britdrive-ai.php";

  const PANEL_WIDTH = 560;

  const PANEL_MARGIN = 28;

  /* =========================================================
       STATE
       ========================================================= */

  let recognition = null;

  let isListening = false;

  let isSending = false;

  let panelCounter = 0;

  /* =========================================================
       PANEL STORAGE
       ========================================================= */

  const panels = new Map();

  let focusedPanelId = null;

  /* =========================================================
       STATUS
       ========================================================= */

  function setState(state) {
    if (!statusLine) {
      return;
    }

    statusLine.classList.remove(
      "is-idle",
      "is-listening",
      "is-thinking",
      "is-answering",
      "is-error",
    );

    statusLine.classList.add("is-" + state);

    statusLine.dataset.state = state;
  }

  setState("idle");

  /* =========================================================
   ACTION BUTTON
   ========================================================= */

  function updateActionButton() {
    if (isSending) {
      actionButton.innerHTML = '<i class="fas fa-ellipsis-h"></i>';

      actionButton.setAttribute("aria-label", "BritDrive AI is thinking");

      actionButton.setAttribute("title", "BritDrive AI is thinking");

      actionButton.classList.add("is-thinking");

      actionButton.classList.remove("is-listening");

      return;
    }

    if (isListening) {
      actionButton.innerHTML = '<i class="fas fa-stop"></i>';

      actionButton.setAttribute("aria-label", "Stop listening");

      actionButton.setAttribute("title", "Stop listening");

      actionButton.classList.remove("is-thinking");

      actionButton.classList.add("is-listening");

      return;
    }

    actionButton.classList.remove("is-thinking", "is-listening");

    /* AI is speaking: show STOP. */
    if ("speechSynthesis" in window && window.speechSynthesis.speaking) {
      actionButton.innerHTML = '<i class="fas fa-stop"></i>';

      actionButton.setAttribute("aria-label", "Stop speaking");

      actionButton.setAttribute("title", "Stop speaking");

      return;
    }

    /* Text entered: show SEND. */
    if (input.value.trim()) {
      actionButton.innerHTML = '<i class="fas fa-play"></i>';

      actionButton.setAttribute("aria-label", "Send message");

      actionButton.setAttribute("title", "Send");
    } else {
      actionButton.innerHTML = '<i class="fas fa-microphone"></i>';
      actionButton.setAttribute("aria-label", "Speak");

      actionButton.setAttribute("title", "Speak");
    }
  }

  /* =========================================================
       INPUT
       ========================================================= */

  function autoGrowInput() {
    input.style.height = "auto";

    const maxHeight = 180;

    input.style.height = Math.min(input.scrollHeight, maxHeight) + "px";
  }

  input.addEventListener("input", function () {
    autoGrowInput();

    updateActionButton();
  });

  input.addEventListener("keydown", function (event) {
    if (event.key === "Enter" && !event.shiftKey) {
      event.preventDefault();

      if (!isSending) {
        sendQuestion();
      }
    }
  });

  /* =========================================================
       PANEL ID
       ========================================================= */

  function createPanelId() {
    panelCounter++;

    return "britdriveAiPanel-" + Date.now() + "-" + panelCounter;
  }

  /* =========================================================
       VIEWPORT
       ========================================================= */

  function getWorkspaceCenter() {
    const width = window.innerWidth;

    const height = window.innerHeight;

    /*
     * Keep the focused answer slightly above
     * the composer.
     */
    return {
      x: width / 2,
      y: Math.max(230, height * 0.43),
    };
  }

  /* =========================================================
       NORMALISE POSITION
       ========================================================= */

  function clampPosition(x, y, panel) {
    const rect = panel.getBoundingClientRect();

    const halfWidth = rect.width / 2;

    const halfHeight = rect.height / 2;

    const minX = PANEL_MARGIN + halfWidth;

    const maxX = window.innerWidth - PANEL_MARGIN - halfWidth;

    const minY = PANEL_MARGIN + halfHeight;

    const maxY = window.innerHeight - 110 - halfHeight;

    return {
      x: Math.max(minX, Math.min(maxX, x)),

      y: Math.max(minY, Math.min(maxY, y)),
    };
  }

  /* =========================================================
       SET PANEL POSITION
       ========================================================= */

  function setPanelPosition(panel, x, y, animate = true) {
    const position = clampPosition(x, y, panel);

    if (!animate) {
      panel.style.transition = "none";
    } else {
      panel.style.transition = "";
    }

    panel.style.left = position.x + "px";

    panel.style.top = position.y + "px";
  }

  /* =========================================================
       FOCUS PANEL
       ========================================================= */

  function focusPanel(panelId, animate = true) {
    const panelData = panels.get(panelId);

    if (!panelData) {
      return;
    }

    /*
     * If another panel is focused,
     * move it into a surrounding position.
     */
    if (focusedPanelId && focusedPanelId !== panelId) {
      const previous = panels.get(focusedPanelId);

      if (previous) {
        movePanelToAvailableSpace(previous.panel);

        previous.panel.classList.remove("is-focused");
      }
    }

    const center = getWorkspaceCenter();

    panelData.panel.classList.add("is-focused");

    /*
     * Bring the panel to the front.
     */
    panelData.zIndex = getHighestZIndex() + 1;

    panelData.panel.style.zIndex = panelData.zIndex;

    setPanelPosition(panelData.panel, center.x, center.y, animate);

    focusedPanelId = panelId;
  }

  /* =========================================================
       AVAILABLE POSITION
       ========================================================= */

  function movePanelToAvailableSpace(panel) {
    const rect = panel.getBoundingClientRect();

    const width = window.innerWidth;

    const height = window.innerHeight;

    const existingPositions = Array.from(panels.values())
      .filter(function (item) {
        return item.panel !== panel && item.panel.isConnected;
      })
      .map(function (item) {
        return {
          x: item.panel.offsetLeft,
          y: item.panel.offsetTop,
        };
      });

    const positions = [
      {
        x: width * 0.22,
        y: height * 0.3,
      },

      {
        x: width * 0.78,
        y: height * 0.3,
      },

      {
        x: width * 0.2,
        y: height * 0.68,
      },

      {
        x: width * 0.8,
        y: height * 0.68,
      },

      {
        x: width * 0.5,
        y: height * 0.22,
      },

      {
        x: width * 0.5,
        y: height * 0.72,
      },
    ];

    let selected = positions[0];

    for (let i = 0; i < positions.length; i++) {
      const candidate = positions[i];

      const occupied = existingPositions.some(function (position) {
        return (
          Math.abs(position.x - candidate.x) < rect.width * 0.45 &&
          Math.abs(position.y - candidate.y) < rect.height * 0.45
        );
      });

      if (!occupied) {
        selected = candidate;

        break;
      }
    }

    setPanelPosition(panel, selected.x, selected.y, true);
  }

  /* =========================================================
       Z INDEX
       ========================================================= */

  function getHighestZIndex() {
    let highest = 1041;

    panels.forEach(function (item) {
      const value = parseInt(item.panel.style.zIndex, 10);

      if (Number.isFinite(value) && value > highest) {
        highest = value;
      }
    });

    return highest;
  }

  /* =========================================================
       CREATE PANEL
       ========================================================= */

  function createResponsePanel(question) {
    const panel = document.createElement("article");

    const panelId = createPanelId();

    panel.id = panelId;

    panel.className = "britdrive-ai-response-panel";

    panel.dataset.panelId = panelId;

    /* -----------------------------------------------------
           HEADER
           ----------------------------------------------------- */

    const header = document.createElement("div");

    header.className = "britdrive-ai-panel-header";

    const questionElement = document.createElement("div");

    questionElement.className = "britdrive-ai-panel-question";

    questionElement.textContent = question;

    const closeButton = document.createElement("button");

    closeButton.type = "button";

    closeButton.className = "britdrive-ai-panel-close";

    closeButton.textContent = "×";

    closeButton.setAttribute("aria-label", "Close answer");

    closeButton.title = "Close";

    header.appendChild(questionElement);

    header.appendChild(closeButton);

    /* -----------------------------------------------------
           BODY
           ----------------------------------------------------- */

    const body = document.createElement("div");

    body.className = "britdrive-ai-panel-body";

    panel.appendChild(header);

    panel.appendChild(body);

    overlay.appendChild(panel);

    /* -----------------------------------------------------
           STORE
           ----------------------------------------------------- */

    panels.set(panelId, {
      panel: panel,
      question: question,
      zIndex: getHighestZIndex() + 1,
    });

    panel.style.zIndex = getHighestZIndex() + 1;

    /* -----------------------------------------------------
           CLOSE
           ----------------------------------------------------- */

    closeButton.addEventListener("click", function (event) {
      event.stopPropagation();

      closePanel(panelId);
    });

    /* -----------------------------------------------------
           FOCUS
           ----------------------------------------------------- */

    panel.addEventListener("pointerdown", function () {
      if (!panel.classList.contains("is-focused")) {
        focusPanel(panelId, true);
      }
    });

    /* -----------------------------------------------------
           DRAG
           ----------------------------------------------------- */

    enableDragging(panel, header, panelId);

    return {
      id: panelId,
      panel: panel,
      body: body,
    };
  }

  /* =========================================================
       CLOSE PANEL
       ========================================================= */

  function closePanel(panelId) {
    const panelData = panels.get(panelId);

    if (!panelData) {
      return;
    }

    /*
     * Stop AI speech immediately when a panel is dismissed.
     */
    if ("speechSynthesis" in window && window.speechSynthesis.speaking) {
      window.speechSynthesis.cancel();
    }

    /*
     * Refresh the composer button.
     */
    updateActionButton();

    const panel = panelData.panel;

    panel.classList.add("is-closing");

    if (focusedPanelId === panelId) {
      focusedPanelId = null;
    }

    setTimeout(function () {
      panel.remove();

      panels.delete(panelId);

      /*
       * If the focused panel was closed,
       * focus the newest remaining panel.
       */
      if (!focusedPanelId) {
        const remaining = Array.from(panels.keys());

        if (remaining.length) {
          focusPanel(remaining[remaining.length - 1], true);
        }
      }
    }, 220);
  }

  /* =========================================================
       DRAGGING
       ========================================================= */

  function enableDragging(panel, handle, panelId) {
    let dragging = false;

    let moved = false;

    let pointerId = null;

    let startPointerX = 0;
    let startPointerY = 0;

    let startPanelX = 0;
    let startPanelY = 0;

    /* =====================================================
       POINTER DOWN
       ===================================================== */

    handle.addEventListener("pointerdown", function (event) {
      /*
       * The close button must NEVER start dragging.
       */
      if (event.target.closest(".britdrive-ai-panel-close")) {
        return;
      }

      /*
       * Only respond to primary mouse button.
       *
       * Touch and pen do not have a normal
       * mouse button value, so they are allowed.
       */
      if (event.pointerType === "mouse" && event.button !== 0) {
        return;
      }

      event.preventDefault();

      dragging = true;
      moved = false;

      pointerId = event.pointerId;

      startPointerX = event.clientX;

      startPointerY = event.clientY;

      const rect = panel.getBoundingClientRect();

      startPanelX = rect.left + rect.width / 2;

      startPanelY = rect.top + rect.height / 2;

      /*
       * Focus the panel immediately.
       */
      focusPanel(panelId, false);

      panel.classList.add("is-dragging");

      /*
       * IMPORTANT:
       *
       * Capture the pointer on the HEADER,
       * because the HEADER owns the drag events.
       */
      try {
        handle.setPointerCapture(pointerId);
      } catch (error) {
        /* Pointer capture may not be available. */
      }
    });

    /* =====================================================
       POINTER MOVE
       ===================================================== */

    handle.addEventListener("pointermove", function (event) {
      if (!dragging || event.pointerId !== pointerId) {
        return;
      }

      event.preventDefault();

      const deltaX = event.clientX - startPointerX;

      const deltaY = event.clientY - startPointerY;

      if (Math.abs(deltaX) > 5 || Math.abs(deltaY) > 5) {
        moved = true;
      }

      const newX = startPanelX + deltaX;

      const newY = startPanelY + deltaY;

      /*
       * During dragging we deliberately DO NOT
       * clamp the panel to the viewport.
       *
       * This allows the user to drag it offscreen
       * and dismiss it.
       */
      panel.style.transition = "none";

      panel.style.left = newX + "px";

      panel.style.top = newY + "px";

      /*
       * Small physical movement effect.
       */
      const rotation = Math.max(-3, Math.min(3, deltaX / 100));

      panel.style.transform =
        "translate(-50%, -50%) rotate(" + rotation + "deg)";
    });

    function isMouseAtHorizontalEdge(event) {
      const edgeZone = 15;

      return (
        event.clientX <= edgeZone ||
        event.clientX >= window.innerWidth - edgeZone
      );
    }
    /* =====================================================
       END DRAG
       ===================================================== */

    function finishDrag(event) {
      if (!dragging || event.pointerId !== pointerId) {
        return;
      }

      dragging = false;

      panel.classList.remove("is-dragging");

      try {
        if (handle.hasPointerCapture(pointerId)) {
          handle.releasePointerCapture(pointerId);
        }
      } catch (error) {
        /* Pointer capture may already be released. */
      }

      pointerId = null;

      /*
       * Restore normal transform.
       */
      panel.style.transform = "translate(-50%, -50%)";

      /*
       * DESKTOP:
       * Close only if the mouse is at the left or
       * right viewport edge when the user releases.
       *
       * TOUCH / PEN:
       * Preserve the existing off-screen dismissal.
       */
      const shouldDismiss =
        moved &&
        ((event.pointerType === "mouse" && isMouseAtHorizontalEdge(event)) ||
          (event.pointerType !== "mouse" && isOutsideDismissZone(panel)));

      if (shouldDismiss) {
        closePanel(panelId);

        return;
      }

      /*
       * If it was just a click rather than a drag,
       * focus the panel.
       */
      if (!moved) {
        focusPanel(panelId, true);

        return;
      }

      /*
       * A completed drag remains where the user
       * dropped it.
       */
      panel.style.transition = "";
    }

    /* =====================================================
       POINTER UP
       ===================================================== */

    handle.addEventListener("pointerup", finishDrag);

    /* =====================================================
       POINTER CANCEL
       ===================================================== */

    handle.addEventListener("pointercancel", function (event) {
      if (event.pointerId !== pointerId) {
        return;
      }

      dragging = false;

      pointerId = null;

      panel.classList.remove("is-dragging");

      panel.style.transform = "translate(-50%, -50%)";

      panel.style.transition = "";
    });

    /* =====================================================
       CLOSE BUTTON PROTECTION
       ===================================================== */

    const closeButton = panel.querySelector(".britdrive-ai-panel-close");

    if (closeButton) {
      closeButton.addEventListener("pointerdown", function (event) {
        /*
         * Do not allow the header's drag
         * behaviour to begin.
         */
        event.stopPropagation();
      });
    }
  }

  /* =========================================================
       DISMISS ZONE
       ========================================================= */

  function isOutsideDismissZone(panel) {
    const rect = panel.getBoundingClientRect();

    const threshold = 70;

    return (
      rect.right < -threshold ||
      rect.left > window.innerWidth + threshold ||
      rect.bottom < -threshold ||
      rect.top > window.innerHeight + threshold
    );
  }

  /* =========================================================
       RENDER TEXT ANSWER
       ========================================================= */

  function renderTextAnswer(body, response) {
    if (!response) {
      return;
    }

    const text = document.createElement("div");

    text.className = "britdrive-ai-answer-text";

    text.textContent = response;

    body.appendChild(text);
  }

  /* =========================================================
       RENDER METRIC
       ========================================================= */

  function renderMetric(body, data) {
    const metric = document.createElement("div");

    metric.className = "britdrive-ai-metric";

    const label = document.createElement("div");

    label.className = "britdrive-ai-metric-label";

    label.textContent = data.label || "Metric";

    const value = document.createElement("div");

    value.className = "britdrive-ai-metric-value";

    value.textContent = data.formatted_value ?? data.value ?? "0";

    metric.appendChild(label);

    metric.appendChild(value);

    body.appendChild(metric);
  }

  /* =========================================================
       RENDER FINANCIAL SUMMARY
       ========================================================= */

  function renderFinancialSummary(body, data) {
    const summary = document.createElement("div");

    summary.className = "britdrive-ai-financial-grid";

    const items = [
      ["Sales", data.sales_count],

      ["Total Sales Value", data.total_sale_value_formatted],

      ["Refunded", data.total_refunded_formatted],

      ["Net Sales Value", data.net_sales_value_formatted],

      ["Balance Due", data.total_balance_due_formatted],

      ["Total Cost", data.total_cost_formatted],

      ["Net Profit", data.net_profit_formatted],
    ];

    items.forEach(function (item) {
      const card = document.createElement("div");

      card.className = "britdrive-ai-financial-item";

      const label = document.createElement("div");

      label.className = "britdrive-ai-financial-label";

      label.textContent = item[0];

      const value = document.createElement("div");

      value.className = "britdrive-ai-financial-value";

      value.textContent = item[1] ?? "£0.00";

      card.appendChild(label);

      card.appendChild(value);

      summary.appendChild(card);
    });

    body.appendChild(summary);
  }

  /* =========================================================
       PRESENTATION
       ========================================================= */

  function renderPresentation(body, presentation) {
    if (!presentation) {
      return;
    }

    const type = presentation.type;

    const data = presentation.data || {};

    switch (type) {
      case "metric":
        renderMetric(body, data);

        break;

      case "financial_summary":
        renderFinancialSummary(body, data);

        break;

      default:
        break;
    }
  }


/* =========================================================
   CREATE ANSWER
   ========================================================= */

function renderAnswer(question, data) {

    const result = createResponsePanel(question);

    if (data.presentation) {

        /*
         * Structured result: show the presentation only.
         * Avoid repeating its information in text.
         */
        renderPresentation(
            result.body,
            data.presentation
        );

    } else {

        /*
         * No structured result: show normal AI text.
         */
        renderTextAnswer(
            result.body,
            data.response
        );
    }

    /*
     * New answer becomes the
     * focused centre panel.
     */
    focusPanel(result.id, true);

    return result;
}

  /* =========================================================
       ERROR PANEL
       ========================================================= */

  function renderError(question, message) {
    const result = createResponsePanel(question);

    result.panel.classList.add("is-error");

    const text = document.createElement("div");

    text.className = "britdrive-ai-answer-text";

    text.textContent = message;

    result.body.appendChild(text);

    focusPanel(result.id, true);
  }

  /* =========================================================
       SEND QUESTION
       ========================================================= */

  async function sendQuestion() {
    const question = input.value.trim();

    if (!question || isSending) {
      return;
    }

    isSending = true;

    updateActionButton();

    setState("thinking");

    /*
     * Stop speech recognition if still active.
     */
    if (isListening && recognition) {
      try {
        recognition.stop();
      } catch (error) {
        /* Already stopped. */
      }
    }

    try {
      const response = await fetch(API_URL, {
        method: "POST",

        headers: {
          "Content-Type": "application/json",
        },

        body: JSON.stringify({
          question: question,
        }),
      });

      if (!response.ok) {
        throw new Error("BritDriveAI request failed.");
      }

      const data = await response.json();

      if (!data.success) {
        throw new Error(
          data.message || "BritDriveAI could not complete the request.",
        );
      }

      setState("answering");

      renderAnswer(question, data);
console.log("AI response:", data.response);
console.log("Presentation:", data.presentation);
console.log("Text passed to speech:", data.response);

      speak(data.response);

    } catch (error) {
      console.error("BritDriveAI:", error);

      setState("error");

      renderError(
        question,
        error.message || "Something went wrong. Please try again.",
      );

      setTimeout(function () {
        if (!isSending) {
          setState("idle");
        }
      }, 1200);
    } finally {
      input.value = "";

      input.style.height = "auto";

      isSending = false;

      updateActionButton();

      if (!window.speechSynthesis || !window.speechSynthesis.speaking) {
        setState("idle");
      }
    }
  }

  /* =========================================================
       ACTION BUTTON
       ========================================================= */

  actionButton.addEventListener("click", function () {
    /*
     * While AI is speaking,
     * allow the same button to stop speech.
     */
    if ("speechSynthesis" in window && window.speechSynthesis.speaking) {
      window.speechSynthesis.cancel();

      setState("idle");

      updateActionButton();

      return;
    }

    /*
     * If there is text,
     * the button is SEND.
     */
    if (input.value.trim() && !isSending) {
      sendQuestion();

      return;
    }

    /*
     * Otherwise the button is MICROPHONE.
     */
    startListening();
  });

  /* =========================================================
       SPEECH RECOGNITION
       ========================================================= */

  function initialiseRecognition() {
    const SpeechRecognition =
      window.SpeechRecognition || window.webkitSpeechRecognition;

    if (!SpeechRecognition) {
      return null;
    }

    const instance = new SpeechRecognition();

    instance.lang = "en-GB";

    instance.continuous = false;

    instance.interimResults = false;

    instance.maxAlternatives = 1;

    instance.onstart = function () {
      isListening = true;

      setState("listening");

      updateActionButton();
    };

    instance.onresult = function (event) {
      const transcript = event.results[0][0].transcript;

      input.value = transcript;

      autoGrowInput();

      updateActionButton();
    };

    instance.onerror = function (event) {
      console.error("BritDriveAI voice:", event.error);

      isListening = false;

      setState("error");

      updateActionButton();

      setTimeout(function () {
        if (!isSending) {
          setState("idle");
        }
      }, 1000);
    };

    instance.onend = function () {
      isListening = false;

      updateActionButton();

      /*
       * IMPORTANT:
       *
       * Voice input is automatically
       * submitted when recognition ends.
       */
      if (input.value.trim() && !isSending) {
        sendQuestion();

        return;
      }

      if (!isSending) {
        setState("idle");
      }
    };

    return instance;
  }

  recognition = initialiseRecognition();

  /* =========================================================
       START LISTENING
       ========================================================= */

  function startListening() {
    if (!recognition) {
      setState("error");

      setTimeout(function () {
        setState("idle");
      }, 1200);

      return;
    }

    if (isListening) {
      try {
        recognition.stop();
      } catch (error) {
        /* Already stopped. */
      }

      return;
    }

    try {
      /*
       * Clear any previous text.
       */
      input.value = "";

      autoGrowInput();

      updateActionButton();

      recognition.start();
    } catch (error) {
      console.error("BritDriveAI microphone:", error);

      setState("error");

      setTimeout(function () {
        setState("idle");
      }, 1000);
    }
  }

  /* =========================================================
       SPEECH SYNTHESIS
       ========================================================= */

  function getPreferredVoice() {
    if (!("speechSynthesis" in window)) {
      return null;
    }

    const voices = window.speechSynthesis.getVoices();

    if (!voices.length) {
      return null;
    }

    return (
      voices.find(function (voice) {
        return voice.lang === "en-GB";
      }) ||
      voices.find(function (voice) {
        return voice.lang && voice.lang.startsWith("en-GB");
      }) ||
      voices.find(function (voice) {
        return voice.lang && voice.lang.startsWith("en");
      }) ||
      voices[0]
    );
  }

  function speak(text) {
     text = text
        .replace(/\*\*/g, '')
        .replace(/\*/g, '')
        .replace(/#{1,6}\s?/g, '')
        .replace(/`/g, '')
        .trim();
    if (!text || !("speechSynthesis" in window)) {
      setState("idle");

      return;
    }

    window.speechSynthesis.cancel();

    const utterance = new SpeechSynthesisUtterance(text);

    const voice = getPreferredVoice();

    if (voice) {
      utterance.voice = voice;
    }

    utterance.lang = "en-GB";

    utterance.rate = 1;

    utterance.pitch = 1;

    utterance.onend = function () {
      setState("idle");
      updateActionButton();
    };

    utterance.onerror = function () {
      setState("idle");
      updateActionButton();
    };

    window.speechSynthesis.speak(utterance);
  }

  /* =========================================================
       WINDOW RESIZE
       ========================================================= */

  window.addEventListener("resize", function () {
    panels.forEach(function (item) {
      const rect = item.panel.getBoundingClientRect();

      const currentX = rect.left + rect.width / 2;

      const currentY = rect.top + rect.height / 2;

      setPanelPosition(item.panel, currentX, currentY, false);
    });

    if (focusedPanelId) {
      const focused = panels.get(focusedPanelId);

      if (focused) {
        const center = getWorkspaceCenter();

        setPanelPosition(focused.panel, center.x, center.y, false);
      }
    }
  });

  /* =========================================================
       SPEECH VOICE LOADING
       ========================================================= */

  if ("speechSynthesis" in window) {
    window.speechSynthesis.addEventListener("voiceschanged", function () {
      window.speechSynthesis.getVoices();
    });
  }

  /* =========================================================
       INITIALISE
       ========================================================= */

  autoGrowInput();

  updateActionButton();
});
