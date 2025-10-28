panel.plugin("asyntai/ai-chatbot", {
  components: {
    "k-asyntai-view": {
      props: {
        title: String,
        connected: Boolean,
        statusColor: String,
        statusText: String,
        accountEmail: String,
        connectUrl: String,
        saveUrl: String,
        resetUrl: String,
        expectedOrigin: String
      },
      template: `
        <k-panel-inside>
          <k-view class="k-asyntai-view">
            <k-header>
              {{ title }}
            </k-header>
            
            <div v-if="alertMessage" style="padding: 1rem; margin-bottom: 1.5rem; border-left: 4px solid; border-radius: 4px;" :style="{ borderColor: alertTheme === 'positive' ? '#16a34a' : '#dc2626', backgroundColor: alertTheme === 'positive' ? '#f0fdf4' : '#fef2f2' }">
              <p style="margin: 0;" :style="{ color: alertTheme === 'positive' ? '#15803d' : '#991b1b' }">{{ alertMessage }}</p>
            </div>

            <k-box theme="info">
              <k-text>
                <p style="margin: 0; font-size: 1rem;">
                  <strong>Status:</strong> <span :style="{ color: statusColor, fontWeight: '600' }">{{ statusText }}</span><template v-if="connected && accountEmail"> as <strong>{{ accountEmail }}</strong></template><template v-if="connected"> <button @click="resetConnection" style="display: inline; margin-left: 0.5rem; padding: 0.25rem 0.5rem; background-color: transparent; color: #dc2626; border: 1px solid #dc2626; border-radius: 3px; font-size: 0.75rem; cursor: pointer; transition: all 0.2s;" @mouseover="$event.target.style.backgroundColor='#dc2626'; $event.target.style.color='white'" @mouseout="$event.target.style.backgroundColor='transparent'; $event.target.style.color='#dc2626'">Reset</button></template>
                </p>
              </k-text>
            </k-box>

            <div v-if="connected" style="margin-top: 2rem; padding: 2.5rem; border: 1px solid #e5e7eb; border-radius: 8px; background: #ffffff; text-align: center;">
              <h2 style="font-size: 1.5rem; font-weight: 700; margin: 0 0 0.5rem 0; color: #111827;">Asyntai is now enabled</h2>
              <p style="font-size: 1rem; margin: 0 0 1.5rem 0; color: #6b7280;">Set up your AI chatbot, review chat logs and more:</p>
              <div style="display: flex; justify-content: center; margin-bottom: 1.5rem;">
                <button @click="openDashboard" style="padding: 0.75rem 1.5rem; background-color: #16a34a; color: white; border: 1px solid #15803d; border-radius: 4px; font-size: 0.875rem; font-weight: 600; cursor: pointer; transition: all 0.2s;" @mouseover="$event.target.style.backgroundColor='#15803d'" @mouseout="$event.target.style.backgroundColor='#16a34a'">
                  Open Asyntai Panel
                </button>
              </div>
              <p style="margin: 0; font-size: 0.875rem; color: #6b7280;">
                <strong>Tip:</strong> If you want to change how the AI answers, please <a href="https://asyntai.com/dashboard#setup" target="_blank" rel="noopener" style="color: #2563eb; text-decoration: underline;">go here</a>.
              </p>
            </div>

            <div v-if="!connected" style="margin-top: 2rem; padding: 2.5rem; border: 1px solid #e5e7eb; border-radius: 8px; background: #ffffff; text-align: center;">
              <p style="font-size: 1.125rem; margin: 0 0 1.5rem 0; color: #111827;">Create a free Asyntai account or sign in to enable the chatbot</p>
              <div style="display: flex; justify-content: center; margin-bottom: 1rem;">
                <button @click="openPopup" style="padding: 0.75rem 1.5rem; background-color: #16a34a; color: white; border: 1px solid #15803d; border-radius: 4px; font-size: 0.875rem; font-weight: 600; cursor: pointer; transition: all 0.2s;" @mouseover="$event.target.style.backgroundColor='#15803d'" @mouseout="$event.target.style.backgroundColor='#16a34a'">
                  Get started
                </button>
              </div>
              <p style="margin: 0; font-size: 0.875rem; color: #6b7280;">
                If it doesn't work, <a :href="fallbackUrl" @click="onFallbackClick" target="_blank" rel="noopener" style="color: #2563eb; text-decoration: underline;">open the connect window</a>.
              </p>
            </div>
          </k-view>
        </k-panel-inside>
      `,
      data() {
        return {
          alertMessage: '',
          alertTheme: 'positive',
          currentState: ''
        };
      },
      computed: {
        fallbackUrl() {
          if (!this.currentState) {
            return this.connectUrl;
          }
          const base = this.connectUrl;
          return base + (base.indexOf('?') > -1 ? '&' : '?') + 'state=' + encodeURIComponent(this.currentState);
        }
      },
      mounted() {
        // Generate initial state for fallback link
        this.generateState();
      },
      methods: {
        showAlert(msg, ok) {
          this.alertMessage = msg;
          this.alertTheme = ok ? 'positive' : 'negative';
        },
        generateState() {
          this.currentState = 'kirby_' + Math.random().toString(36).substr(2, 9);
        },
        openDashboard() {
          window.open('https://asyntai.com/dashboard', '_blank', 'noopener,noreferrer');
        },
        openPopup() {
          // Generate new state for this connection attempt
          this.generateState();
          const state = this.currentState;
          const base = this.connectUrl;
          const url = base + (base.indexOf('?') > -1 ? '&' : '?') + 'state=' + encodeURIComponent(state);
          const w = 800, h = 720;
          const y = window.top.outerHeight / 2 + window.top.screenY - (h / 2);
          const x = window.top.outerWidth / 2 + window.top.screenX - (w / 2);
          const pop = window.open(url, 'asyntai_connect', `toolbar=no,location=no,status=no,menubar=no,scrollbars=yes,resizable=yes,width=${w},height=${h},top=${y},left=${x}`);
          
          if (!pop) {
            this.showAlert('Popup blocked. Please allow popups or use the link below.', false);
            return;
          }
          this.pollForConnection(state);
        },
        onFallbackClick() {
          // Generate new state and start polling when fallback link is clicked
          this.generateState();
          setTimeout(() => {
            this.pollForConnection(this.currentState);
          }, 1000);
        },
        pollForConnection(state) {
          let attempts = 0;
          const check = () => {
            if (attempts++ > 60) return;
            const script = document.createElement('script');
            const cb = 'asyntai_cb_' + Date.now();
            script.src = this.expectedOrigin + '/connect-status.js?state=' + encodeURIComponent(state) + '&cb=' + cb;
            
            window[cb] = (data) => {
              try { delete window[cb]; } catch(e) {}
              if (data && data.site_id) {
                this.saveConnection(data);
                return;
              }
              setTimeout(check, 500);
            };
            
            script.onerror = () => {
              setTimeout(check, 1000);
            };
            
            document.head.appendChild(script);
          };
          setTimeout(check, 800);
        },
        saveConnection(data) {
          this.showAlert('Asyntai connected. Saving…', true);
          const payload = {
            site_id: data.site_id || '',
            script_url: data.script_url || '',
            account_email: data.account_email || ''
          };
          
          fetch(this.saveUrl, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
          })
          .then(r => {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
          })
          .then(json => {
            if (!json || !json.success) throw new Error((json && json.error) || 'Save failed');
            this.showAlert('Asyntai connected. Chatbot enabled on all pages.', true);
            setTimeout(() => {
              window.location.reload();
            }, 1000);
          })
          .catch(err => {
            this.showAlert('Could not save settings: ' + (err && err.message || err), false);
          });
        },
        resetConnection() {
          if (!confirm('Are you sure you want to reset the Asyntai connection?')) {
            return;
          }
          
          fetch(this.resetUrl, {
            method: 'POST',
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
          })
          .then(r => {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
          })
          .then(() => {
            window.location.reload();
          })
          .catch(err => {
            this.showAlert('Reset failed: ' + (err && err.message || err), false);
          });
        }
      }
    }
  }
});
