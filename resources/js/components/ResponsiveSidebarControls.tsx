import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

export function ResponsiveSidebarControls() {
    const [open, setOpen] = useState(false);
    const toggleRef = useRef<HTMLButtonElement>(null);
    const previousFocus = useRef<HTMLElement | null>(null);
    const sidebar = document.querySelector<HTMLElement>('#main-sidebar');

    useEffect(() => {
        document.body.classList.toggle('sidebar-open', open);

        if (open) {
            previousFocus.current = document.activeElement instanceof HTMLElement ? document.activeElement : null;
            sidebar?.querySelector<HTMLElement>('a[href], button:not([disabled])')?.focus();
        } else {
            previousFocus.current = null;
        }

        return () => {
            document.body.classList.remove('sidebar-open');
        };
    }, [open, sidebar]);

    useEffect(() => {
        const closeAtDesktop = (event: MediaQueryListEvent) => {
            if (event.matches) {
                setOpen(false);
            }
        };
        const media = window.matchMedia('(min-width: 901px)');
        media.addEventListener('change', closeAtDesktop);
        return () => media.removeEventListener('change', closeAtDesktop);
    }, []);

    useEffect(() => {
        if (!open || !sidebar) {
            return;
        }

        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                setOpen(false);
                toggleRef.current?.focus();
                return;
            }
            if (event.key !== 'Tab') {
                return;
            }

            const focusable = sidebar.querySelectorAll<HTMLElement>('a[href], button:not([disabled])');
            const first = focusable.item(0);
            const last = focusable.item(focusable.length - 1);
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last?.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first?.focus();
            }
        };
        const onSidebarClick = (event: MouseEvent) => {
            if (event.target instanceof Element && event.target.closest('a[href]')) {
                setOpen(false);
            }
        };

        document.addEventListener('keydown', onKeyDown);
        sidebar.addEventListener('click', onSidebarClick);
        return () => {
            document.removeEventListener('keydown', onKeyDown);
            sidebar.removeEventListener('click', onSidebarClick);
        };
    }, [open, sidebar]);

    const scrim = open
        ? createPortal(
              <button
                  className="sidebar-scrim"
                  type="button"
                  aria-label="Tutup navigasi"
                  onClick={() => {
                      setOpen(false);
                      previousFocus.current?.focus();
                  }}
              />,
              document.body,
          )
        : null;

    return (
        <>
            <button
                ref={toggleRef}
                className="sidebar-toggle"
                type="button"
                aria-controls="main-sidebar"
                aria-expanded={open}
                aria-label={open ? 'Tutup navigasi' : 'Buka navigasi'}
                onClick={() => setOpen((current) => !current)}
            >
                <span className="sidebar-toggle-bars" aria-hidden="true" />
            </button>
            {scrim}
        </>
    );
}
