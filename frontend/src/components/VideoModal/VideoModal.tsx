/**
 * Video Modal Component
 * Modal overlay for demo video with Vimeo embed
 * Requirements: 6
 */

import React, { useEffect, useRef } from 'react';
import './VideoModal.css';

interface VideoModalProps {
  videoId: string;
  onClose: () => void;
}

export const VideoModal: React.FC<VideoModalProps> = ({ videoId, onClose }) => {
  const modalRef = useRef<HTMLDivElement>(null);
  const contentRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    // Handle Escape key
    const handleEscape = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        onClose();
      }
    };

    // Handle click outside
    const handleClickOutside = (e: MouseEvent) => {
      if (contentRef.current && !contentRef.current.contains(e.target as Node)) {
        onClose();
      }
    };

    document.addEventListener('keydown', handleEscape);
    document.addEventListener('mousedown', handleClickOutside);

    // Prevent body scroll when modal is open
    document.body.style.overflow = 'hidden';

    return () => {
      document.removeEventListener('keydown', handleEscape);
      document.removeEventListener('mousedown', handleClickOutside);
      document.body.style.overflow = '';
    };
  }, [onClose]);

  return (
    <div className="video-modal-overlay" ref={modalRef}>
      <div className="video-modal-content" ref={contentRef}>
        <button 
          className="video-modal-close" 
          onClick={onClose}
          aria-label="Close video"
        >
          ×
        </button>
        
        <div className="video-wrapper">
          <iframe
            src={`https://player.vimeo.com/video/${videoId}?title=0&byline=0&portrait=0`}
            width="640"
            height="360"
            frameBorder="0"
            allow="autoplay; fullscreen; picture-in-picture"
            allowFullScreen
            title="Product Demo Video"
          />
        </div>
      </div>
    </div>
  );
};
