# Add pnpm global bin subdir to PATH — Dockerfile.pnpm only adds PNPM_HOME itself,
# but pnpm places executables in PNPM_HOME/bin.
RUN echo 'export PATH="/mnt/ddev-global-cache/pnpm/bin:$PATH"' >> /etc/bash.bashrc && \
    echo 'export PATH="/mnt/ddev-global-cache/pnpm/bin:$PATH"' >> /etc/bash.nointeractive.bashrc