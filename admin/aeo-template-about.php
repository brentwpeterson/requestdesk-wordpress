<?php
/**
 * AEO About Page Template Content
 * Comprehensive About page template with AEO/GEO optimization features
 */

function requestdesk_get_about_aeo_template() {
    $template_content = <<<'EOD'
<!-- wp:heading {"level":1,"style":{"color":{"text":"#ff0000"}}} -->
<h1 class="wp-block-heading has-text-color" style="color:#ff0000">🚀 AEO/GEO OPTIMIZED ABOUT PAGE TEMPLATE</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"color":{"background":"#fff3cd","text":"#856404"},"spacing":{"padding":{"top":"20px","bottom":"20px","left":"20px","right":"20px"}},"border":{"radius":"8px","color":"#ffeaa7","width":"1px"}}} -->
<p class="has-text-color has-background has-border-color" style="border-color:#ffeaa7;border-width:1px;border-radius:8px;background-color:#fff3cd;color:#856404;padding-top:20px;padding-bottom:20px;padding-left:20px;padding-right:20px"><strong>📋 ABOUT PAGE AEO/GEO TEMPLATE:</strong> This template creates an authority-building About page with complete AEO (Answer Engine Optimization) and GEO (Generative Engine Optimization) features. Look for <mark>[CUSTOMIZE]</mark> tags throughout to personalize for other businesses.</p>
<!-- /wp:paragraph -->

<!-- wp:details {"summary":"📖 About Page AEO/GEO Setup Checklist - Click to Expand"} -->
<details class="wp-block-details"><summary><strong>📖 About Page AEO/GEO Setup Checklist - Click to Expand</strong></summary><!-- wp:list -->
<ul class="wp-block-list">
<li><strong>✅ Person/Organization Schema:</strong> Complete business leader and company information</li>
<li><strong>✅ About Page Schema:</strong> Structured data for company story and mission</li>
<li><strong>✅ FAQ Schema:</strong> Common questions about the company</li>
<li><strong>✅ Team Schema:</strong> Key team members with credentials</li>
<li><strong>✅ Achievement Schema:</strong> Awards, certifications, and milestones</li>
<li><strong>✅ E-E-A-T Signals:</strong> Expertise, Experience, Authoritativeness, Trust</li>
<li><strong>✅ Internal Linking:</strong> Strategic links to services and case studies</li>
<li><strong>✅ Trust Signals:</strong> Certifications, awards, client logos</li>
<li><strong>✅ Semantic HTML:</strong> Proper heading hierarchy and landmarks</li>
<li><strong>✅ Accessibility:</strong> Alt text, ARIA labels, keyboard navigation</li>
<li><strong>🔧 TODO:</strong> Add real team photos and credentials</li>
<li><strong>🔧 TODO:</strong> Update achievement dates and details</li>
<li><strong>🔧 TODO:</strong> Add specific client testimonials</li>
</ul>
<!-- /wp:list --></details>
<!-- /wp:details -->

<!-- wp:paragraph {"style":{"color":{"background":"#e8f4fd","text":"#0c5460"},"spacing":{"padding":{"top":"15px","bottom":"15px","left":"15px","right":"15px"}},"border":{"radius":"6px"}}} -->
<p class="has-text-color has-background" style="border-radius:6px;background-color:#e8f4fd;color:#0c5460;padding-top:15px;padding-bottom:15px;padding-left:15px;padding-right:15px"><strong>📝 OPTIMIZED META DESCRIPTION:</strong> "Learn about [Your Company Name]'s mission to drive business growth through expert content marketing and SEO. Meet our team of experienced writers, designers, and strategists. Trusted by 1,000+ companies worldwide."</p>
<!-- /wp:paragraph -->

EOD;
    $template_content .= requestdesk_get_action_instruction_block('meta_description');
    $template_content .= <<<'EOD'

<!-- wp:html -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "AboutPage",
  "mainEntity": {
    "@type": "Organization",
    "name": "[Your Company Name]",
    "alternateName": "[CUSTOMIZE: Add your business name]",
    "url": "<?php echo esc_url(home_url()); ?>",
    "logo": {
      "@type": "ImageObject",
      "url": "<?php echo esc_url(wp_upload_dir()['baseurl']); ?>/logo.png"
    },
    "description": "[Your Company Name] is a leading content marketing and SEO agency combining human expertise with AI-powered insights to deliver measurable business growth for companies worldwide.",
    "foundingDate": "2018",
    "founder": {
      "@type": "Person",
      "name": "[CUSTOMIZE: Founder Name]",
      "jobTitle": "CEO & Founder",
      "description": "[CUSTOMIZE: Brief founder bio and credentials]"
    },
    "numberOfEmployees": "25",
    "areaServed": {
      "@type": "Place",
      "name": "Worldwide"
    },
    "serviceType": ["Content Marketing", "SEO Optimization", "AI-Powered Analytics", "Digital Strategy"],
    "aggregateRating": {
      "@type": "AggregateRating",
      "ratingValue": "4.9",
      "reviewCount": "500",
      "bestRating": "5",
      "worstRating": "1"
    },
    "award": [
      "[CUSTOMIZE: Industry Award 1]",
      "[CUSTOMIZE: Industry Award 2]"
    ]
  }
}
</script>
<!-- /wp:html -->

<!-- wp:html -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    {
      "@type": "ListItem",
      "position": 1,
      "name": "Home",
      "item": "<?php echo esc_url(home_url()); ?>"
    },
    {
      "@type": "ListItem",
      "position": 2,
      "name": "About",
      "item": "<?php echo esc_url(home_url('/about')); ?>"
    }
  ]
}
</script>
<!-- /wp:html -->

<!-- wp:spacer {"height":"40px"} -->
<div style="height:40px" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->

<!-- wp:heading {"level":2,"style":{"typography":{"fontSize":"48px","fontWeight":"700"},"color":{"text":"#2c3e50"}}} -->
<h2 class="wp-block-heading has-text-color" style="color:#2c3e50;font-size:48px;font-weight:700">About [Your Company Name]</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"color":{"background":"#f8f9fa","text":"#495057"},"spacing":{"padding":{"top":"20px","bottom":"20px","left":"20px","right":"20px"}},"border":{"radius":"8px","width":"0px","style":"none"},"typography":{"fontSize":"18px","lineHeight":"1.7"}}} -->
<p class="has-text-color has-background" style="border-style:none;border-width:0px;border-radius:8px;background-color:#f8f9fa;color:#495057;font-size:18px;line-height:1.7;padding-top:20px;padding-bottom:20px;padding-left:20px;padding-right:20px"><strong>🎯 Our Mission:</strong> We drive sustainable business growth through expert content marketing, SEO optimization, and AI-powered digital strategies. [CUSTOMIZE: Add your company mission statement here]</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"18px","lineHeight":"1.6"},"spacing":{"margin":{"top":"30px"}}}} -->
<p style="margin-top:30px;font-size:18px;line-height:1.6">Since 2018, [Your Company Name] has been the trusted partner for businesses seeking to amplify their digital presence and drive meaningful growth. Our proven methodology combines human creativity with cutting-edge AI insights to deliver content that not only engages audiences but converts them into loyal customers.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"18px","lineHeight":"1.6"}}} -->
<p style="font-size:18px;line-height:1.6">What sets us apart is our commitment to measurable results. We don't just create content—we engineer growth strategies that deliver tangible ROI. With over 60,000 projects completed and a 4.9/5 client satisfaction rating, we've proven that the right content strategy can transform businesses.</p>
<!-- /wp:paragraph -->

<!-- wp:spacer {"height":"50px"} -->
<div style="height:50px" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->

EOD;
    $template_content .= requestdesk_get_action_instruction_block('company_story');
    $template_content .= <<<'EOD'

<!-- wp:heading {"level":2,"style":{"typography":{"fontSize":"36px","fontWeight":"600"},"color":{"text":"#2c3e50"}}} -->
<h2 class="wp-block-heading has-text-color" style="color:#2c3e50;font-size:36px;font-weight:600">Our Story</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"color":{"background":"#e8f4fd","text":"#0c5460"},"spacing":{"padding":{"top":"15px","bottom":"15px","left":"15px","right":"15px"}},"border":{"radius":"6px"},"typography":{"fontSize":"16px"}}} -->
<p class="has-text-color has-background" style="border-radius:6px;background-color:#e8f4fd;color:#0c5460;font-size:16px;padding-top:15px;padding-bottom:15px;padding-left:15px;padding-right:15px"><strong>💡 Educational Note:</strong> This section builds authority and trust through storytelling. Share your company's origin story, key milestones, and what drives your team to help clients succeed.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"18px","lineHeight":"1.6"},"spacing":{"margin":{"top":"20px"}}}} -->
<p style="margin-top:20px;font-size:18px;line-height:1.6">[Your Company Name] was born from a simple observation: businesses were struggling to cut through the digital noise. Traditional marketing approaches weren't delivering the results companies needed to thrive in an increasingly competitive landscape.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"18px","lineHeight":"1.6"}}} -->
<p style="font-size:18px;line-height:1.6">Our founder, [CUSTOMIZE: Founder Name], recognized that the future of marketing lay in the perfect fusion of human creativity and artificial intelligence. After years of refining our methodology, we've developed a proprietary approach that consistently delivers exceptional results for our clients.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"18px","lineHeight":"1.6"}}} -->
<p style="font-size:18px;line-height:1.6">[CUSTOMIZE: Add 2-3 paragraphs about your company's specific journey, key milestones, challenges overcome, and what drives your mission. Include specific dates, achievements, and growth metrics to build credibility.]</p>
<!-- /wp:paragraph -->

<!-- wp:spacer {"height":"50px"} -->
<div style="height:50px" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->

EOD;
    $template_content .= requestdesk_get_action_instruction_block('company_story', '🏢 Company Values Optimization');
    $template_content .= <<<'EOD'

<!-- wp:heading {"level":2,"style":{"typography":{"fontSize":"36px","fontWeight":"600"},"color":{"text":"#2c3e50"}}} -->
<h2 class="wp-block-heading has-text-color" style="color:#2c3e50;font-size:36px;font-weight:600">Our Values & Approach</h2>
<!-- /wp:heading -->

<!-- wp:columns {"style":{"spacing":{"margin":{"top":"30px"},"blockGap":{"top":"30px","left":"30px"}}}} -->
<div class="wp-block-columns" style="margin-top:30px"><!-- wp:column {"style":{"border":{"radius":"12px","color":"#e9ecef","width":"1px"},"spacing":{"padding":{"top":"30px","bottom":"30px","left":"25px","right":"25px"}}}} -->
<div class="wp-block-column has-border-color" style="border-color:#e9ecef;border-width:1px;border-radius:12px;padding-top:30px;padding-right:25px;padding-bottom:30px;padding-left:25px"><!-- wp:heading {"level":3,"style":{"typography":{"fontSize":"24px","fontWeight":"600"},"color":{"text":"#3498db"}}} -->
<h3 class="wp-block-heading has-text-color" style="color:#3498db;font-size:24px;font-weight:600">🎯 Results-Driven</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"16px","lineHeight":"1.6"}}} -->
<p style="font-size:16px;line-height:1.6">Every strategy we develop is anchored in measurable outcomes. We believe in transparent reporting and data-driven decision making that delivers real ROI for our clients.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column {"style":{"border":{"radius":"12px","color":"#e9ecef","width":"1px"},"spacing":{"padding":{"top":"30px","bottom":"30px","left":"25px","right":"25px"}}}} -->
<div class="wp-block-column has-border-color" style="border-color:#e9ecef;border-width:1px;border-radius:12px;padding-top:30px;padding-right:25px;padding-bottom:30px;padding-left:25px"><!-- wp:heading {"level":3,"style":{"typography":{"fontSize":"24px","fontWeight":"600"},"color":{"text":"#e74c3c"}}} -->
<h3 class="wp-block-heading has-text-color" style="color:#e74c3c;font-size:24px;font-weight:600">🤝 Partnership</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"16px","lineHeight":"1.6"}}} -->
<p style="font-size:16px;line-height:1.6">We don't just work for you—we work with you. Our collaborative approach ensures that every campaign aligns perfectly with your business goals and brand values.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column {"style":{"border":{"radius":"12px","color":"#e9ecef","width":"1px"},"spacing":{"padding":{"top":"30px","bottom":"30px","left":"25px","right":"25px"}}}} -->
<div class="wp-block-column has-border-color" style="border-color:#e9ecef;border-width:1px;border-radius:12px;padding-top:30px;padding-right:25px;padding-bottom:30px;padding-left:25px"><!-- wp:heading {"level":3,"style":{"typography":{"fontSize":"24px","fontWeight":"600"},"color":{"text":"#f39c12"}}} -->
<h3 class="wp-block-heading has-text-color" style="color:#f39c12;font-size:24px;font-weight:600">🚀 Innovation</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"16px","lineHeight":"1.6"}}} -->
<p style="font-size:16px;line-height:1.6">We stay ahead of industry trends and leverage cutting-edge AI tools to give our clients a competitive advantage in the digital marketplace.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:spacer {"height":"50px"} -->
<div style="height:50px" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->

EOD;
    $template_content .= requestdesk_get_action_instruction_block('team_profiles');
    $template_content .= <<<'EOD'

<!-- wp:heading {"level":2,"style":{"typography":{"fontSize":"36px","fontWeight":"600"},"color":{"text":"#2c3e50"}}} -->
<h2 class="wp-block-heading has-text-color" style="color:#2c3e50;font-size:36px;font-weight:600">Meet Our Leadership Team</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"color":{"background":"#e8f4fd","text":"#0c5460"},"spacing":{"padding":{"top":"15px","bottom":"15px","left":"15px","right":"15px"}},"border":{"radius":"6px"},"typography":{"fontSize":"16px"}}} -->
<p class="has-text-color has-background" style="border-radius:6px;background-color:#e8f4fd;color:#0c5460;font-size:16px;padding-top:15px;padding-bottom:15px;padding-left:15px;padding-right:15px"><strong>🏆 E-E-A-T Building:</strong> This section establishes Expertise, Experience, Authoritativeness, and Trust by showcasing team credentials and achievements. Replace with your actual team members and their accomplishments.</p>
<!-- /wp:paragraph -->

<!-- wp:columns {"style":{"spacing":{"margin":{"top":"40px"},"blockGap":{"top":"40px","left":"40px"}}}} -->
<div class="wp-block-columns" style="margin-top:40px"><!-- wp:column {"style":{"border":{"radius":"16px","color":"#f8f9fa","width":"2px"},"spacing":{"padding":{"top":"30px","bottom":"30px","left":"30px","right":"30px"}}}} -->
<div class="wp-block-column has-border-color" style="border-color:#f8f9fa;border-width:2px;border-radius:16px;padding-top:30px;padding-right:30px;padding-bottom:30px;padding-left:30px"><!-- wp:image {"width":"120px","height":"120px","scale":"cover","sizeSlug":"full","style":{"border":{"radius":"60px"}}} -->
<figure class="wp-block-image size-full is-resized" style="border-radius:60px"><img src="<?php echo esc_url(wp_upload_dir()['baseurl']); ?>/team-placeholder.jpg" alt="[CUSTOMIZE: CEO Name] - CEO & Founder" style="border-radius:60px;object-fit:cover;width:120px;height:120px"/></figure>
<!-- /wp:image -->

<!-- wp:heading {"level":3,"style":{"typography":{"fontSize":"24px","fontWeight":"600"},"color":{"text":"#2c3e50"},"spacing":{"margin":{"top":"20px"}}}} -->
<h3 class="wp-block-heading has-text-color" style="color:#2c3e50;margin-top:20px;font-size:24px;font-weight:600">[CUSTOMIZE: CEO Name]</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"16px","fontWeight":"500"},"color":{"text":"#6c757d"}}} -->
<p class="has-text-color" style="color:#6c757d;font-size:16px;font-weight:500">CEO & Founder</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"15px","lineHeight":"1.6"},"spacing":{"margin":{"top":"15px"}}}} -->
<p style="margin-top:15px;font-size:15px;line-height:1.6">[CUSTOMIZE: 2-3 sentences about CEO background, expertise, and key achievements. Include relevant certifications or industry recognition.]</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column {"style":{"border":{"radius":"16px","color":"#f8f9fa","width":"2px"},"spacing":{"padding":{"top":"30px","bottom":"30px","left":"30px","right":"30px"}}}} -->
<div class="wp-block-column has-border-color" style="border-color:#f8f9fa;border-width:2px;border-radius:16px;padding-top:30px;padding-right:30px;padding-bottom:30px;padding-left:30px"><!-- wp:image {"width":"120px","height":"120px","scale":"cover","sizeSlug":"full","style":{"border":{"radius":"60px"}}} -->
<figure class="wp-block-image size-full is-resized" style="border-radius:60px"><img src="<?php echo esc_url(wp_upload_dir()['baseurl']); ?>/team-placeholder.jpg" alt="[CUSTOMIZE: CTO Name] - Chief Technology Officer" style="border-radius:60px;object-fit:cover;width:120px;height:120px"/></figure>
<!-- /wp:image -->

<!-- wp:heading {"level":3,"style":{"typography":{"fontSize":"24px","fontWeight":"600"},"color":{"text":"#2c3e50"},"spacing":{"margin":{"top":"20px"}}}} -->
<h3 class="wp-block-heading has-text-color" style="color:#2c3e50;margin-top:20px;font-size:24px;font-weight:600">[CUSTOMIZE: CTO Name]</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"16px","fontWeight":"500"},"color":{"text":"#6c757d"}}} -->
<p class="has-text-color" style="color:#6c757d;font-size:16px;font-weight:500">Chief Technology Officer</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"15px","lineHeight":"1.6"},"spacing":{"margin":{"top":"15px"}}}} -->
<p style="margin-top:15px;font-size:15px;line-height:1.6">[CUSTOMIZE: 2-3 sentences about CTO background, technical expertise, and innovations. Include relevant technical certifications or achievements.]</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:spacer {"height":"50px"} -->
<div style="height:50px" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->

EOD;
    $template_content .= requestdesk_get_action_instruction_block('achievement_stats');
    $template_content .= <<<'EOD'

<!-- wp:heading {"level":2,"style":{"typography":{"fontSize":"36px","fontWeight":"600"},"color":{"text":"#2c3e50"}}} -->
<h2 class="wp-block-heading has-text-color" style="color:#2c3e50;font-size:36px;font-weight:600">Our Achievements & Recognition</h2>
<!-- /wp:heading -->

<!-- wp:columns {"style":{"spacing":{"margin":{"top":"30px"},"blockGap":{"top":"20px","left":"20px"}}}} -->
<div class="wp-block-columns" style="margin-top:30px"><!-- wp:column {"style":{"border":{"radius":"12px","color":"#e8f4fd","width":"2px"},"spacing":{"padding":{"top":"25px","bottom":"25px","left":"25px","right":"25px"}},"color":{"background":"#f8fcff"}}} -->
<div class="wp-block-column has-background has-border-color" style="border-color:#e8f4fd;border-width:2px;border-radius:12px;background-color:#f8fcff;padding-top:25px;padding-right:25px;padding-bottom:25px;padding-left:25px"><!-- wp:heading {"level":3,"style":{"typography":{"fontSize":"32px","fontWeight":"700"},"color":{"text":"#3498db"}}} -->
<h3 class="wp-block-heading has-text-color" style="color:#3498db;font-size:32px;font-weight:700">60,000+</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"16px","fontWeight":"600"},"color":{"text":"#2c3e50"}}} -->
<p class="has-text-color" style="color:#2c3e50;font-size:16px;font-weight:600">Projects Delivered</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column {"style":{"border":{"radius":"12px","color":"#e8f5e8","width":"2px"},"spacing":{"padding":{"top":"25px","bottom":"25px","left":"25px","right":"25px"}},"color":{"background":"#f8fff8"}}} -->
<div class="wp-block-column has-background has-border-color" style="border-color:#e8f5e8;border-width:2px;border-radius:12px;background-color:#f8fff8;padding-top:25px;padding-right:25px;padding-bottom:25px;padding-left:25px"><!-- wp:heading {"level":3,"style":{"typography":{"fontSize":"32px","fontWeight":"700"},"color":{"text":"#27ae60"}}} -->
<h3 class="wp-block-heading has-text-color" style="color:#27ae60;font-size:32px;font-weight:700">★ 4.9/5</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"16px","fontWeight":"600"},"color":{"text":"#2c3e50"}}} -->
<p class="has-text-color" style="color:#2c3e50;font-size:16px;font-weight:600">Client Satisfaction</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column {"style":{"border":{"radius":"12px","color":"#fdf2e9","width":"2px"},"spacing":{"padding":{"top":"25px","bottom":"25px","left":"25px","right":"25px"}},"color":{"background":"#fffaf5"}}} -->
<div class="wp-block-column has-background has-border-color" style="border-color:#fdf2e9;border-width:2px;border-radius:12px;background-color:#fffaf5;padding-top:25px;padding-right:25px;padding-bottom:25px;padding-left:25px"><!-- wp:heading {"level":3,"style":{"typography":{"fontSize":"32px","fontWeight":"700"},"color":{"text":"#f39c12"}}} -->
<h3 class="wp-block-heading has-text-color" style="color:#f39c12;font-size:32px;font-weight:700">1,000+</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"16px","fontWeight":"600"},"color":{"text":"#2c3e50"}}} -->
<p class="has-text-color" style="color:#2c3e50;font-size:16px;font-weight:600">Companies Served</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column {"style":{"border":{"radius":"12px","color":"#fdeaea","width":"2px"},"spacing":{"padding":{"top":"25px","bottom":"25px","left":"25px","right":"25px"}},"color":{"background":"#fff5f5"}}} -->
<div class="wp-block-column has-background has-border-color" style="border-color:#fdeaea;border-width:2px;border-radius:12px;background-color:#fff5f5;padding-top:25px;padding-right:25px;padding-bottom:25px;padding-left:25px"><!-- wp:heading {"level":3,"style":{"typography":{"fontSize":"32px","fontWeight":"700"},"color":{"text":"#e74c3c"}}} -->
<h3 class="wp-block-heading has-text-color" style="color:#e74c3c;font-size:32px;font-weight:700">6+ Years</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"16px","fontWeight":"600"},"color":{"text":"#2c3e50"}}} -->
<p class="has-text-color" style="color:#2c3e50;font-size:16px;font-weight:600">Industry Experience</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:spacer {"height":"50px"} -->
<div style="height:50px" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->

<!-- wp:heading {"level":2,"style":{"typography":{"fontSize":"36px","fontWeight":"600"},"color":{"text":"#2c3e50"}}} -->
<h2 class="wp-block-heading has-text-color" style="color:#2c3e50;font-size:36px;font-weight:600">Frequently Asked Questions</h2>
<!-- /wp:heading -->

<!-- wp:html -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "What makes [Your Company Name] different from other agencies?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "We combine human expertise with AI-powered insights to deliver exceptional results. Our proprietary methodology and data-driven approach ensure measurable ROI for every client engagement."
      }
    },
    {
      "@type": "Question",
      "name": "How long has [Your Company Name] been in business?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Since 2018, we've been helping businesses achieve sustainable growth through strategic content marketing and SEO optimization. Our experience spans over 60,000 successful projects."
      }
    },
    {
      "@type": "Question",
      "name": "What industries do you work with?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "We serve clients across all industries, from e-commerce and SaaS to professional services and manufacturing. Our diverse experience allows us to adapt our strategies to any market or audience."
      }
    }
  ]
}
</script>
<!-- /wp:html -->

<!-- wp:details {"summary":"What makes [Your Company Name] different from other agencies?","style":{"border":{"radius":"8px","color":"#e9ecef","width":"1px"},"spacing":{"padding":{"top":"20px","bottom":"20px","left":"20px","right":"20px"}}}} -->
<details class="wp-block-details has-border-color" style="border-color:#e9ecef;border-width:1px;border-radius:8px;padding-top:20px;padding-right:20px;padding-bottom:20px;padding-left:20px"><summary><strong>What makes [Your Company Name] different from other agencies?</strong></summary><!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"15px"}}}} -->
<p style="margin-top:15px">We combine human expertise with AI-powered insights to deliver exceptional results. Our proprietary methodology and data-driven approach ensure measurable ROI for every client engagement. With over 60,000 projects completed, we've proven that our unique blend of creativity and technology drives sustainable business growth.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:details {"summary":"How long has [Your Company Name] been in business?","style":{"border":{"radius":"8px","color":"#e9ecef","width":"1px"},"spacing":{"padding":{"top":"20px","bottom":"20px","left":"20px","right":"20px"}}}} -->
<details class="wp-block-details has-border-color" style="border-color:#e9ecef;border-width:1px;border-radius:8px;padding-top:20px;padding-right:20px;padding-bottom:20px;padding-left:20px"><summary><strong>How long has [Your Company Name] been in business?</strong></summary><!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"15px"}}}} -->
<p style="margin-top:15px">Since 2018, we've been helping businesses achieve sustainable growth through strategic content marketing and SEO optimization. Our experience spans over 60,000 successful projects across diverse industries and markets worldwide.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:details {"summary":"What industries do you work with?","style":{"border":{"radius":"8px","color":"#e9ecef","width":"1px"},"spacing":{"padding":{"top":"20px","bottom":"20px","left":"20px","right":"20px"}}}} -->
<details class="wp-block-details has-border-color" style="border-color:#e9ecef;border-width:1px;border-radius:8px;padding-top:20px;padding-right:20px;padding-bottom:20px;padding-left:20px"><summary><strong>What industries do you work with?</strong></summary><!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"15px"}}}} -->
<p style="margin-top:15px">We serve clients across all industries, from e-commerce and SaaS to professional services and manufacturing. Our diverse experience allows us to adapt our strategies to any market or audience, ensuring effective results regardless of your business sector.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:details {"summary":"[CUSTOMIZE: Common Question About Your Company]","style":{"border":{"radius":"8px","color":"#e9ecef","width":"1px"},"spacing":{"padding":{"top":"20px","bottom":"20px","left":"20px","right":"20px"}}}} -->
<details class="wp-block-details has-border-color" style="border-color:#e9ecef;border-width:1px;border-radius:8px;padding-top:20px;padding-right:20px;padding-bottom:20px;padding-left:20px"><summary><strong>[CUSTOMIZE: Common Question About Your Company]</strong></summary><!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"15px"}}}} -->
<p style="margin-top:15px">[CUSTOMIZE: Provide a comprehensive answer that addresses common concerns or questions your prospects have about your company, services, or approach.]</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:spacer {"height":"50px"} -->
<div style="height:50px" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->

<!-- wp:heading {"level":2,"style":{"typography":{"fontSize":"36px","fontWeight":"600"},"color":{"text":"#2c3e50"}}} -->
<h2 class="wp-block-heading has-text-color" style="color:#2c3e50;font-size:36px;font-weight:600">Ready to Work Together?</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"color":{"background":"#e8f4fd","text":"#0c5460"},"spacing":{"padding":{"top":"15px","bottom":"15px","left":"15px","right":"15px"}},"border":{"radius":"6px"},"typography":{"fontSize":"16px"}}} -->
<p class="has-text-color has-background" style="border-radius:6px;background-color:#e8f4fd;color:#0c5460;font-size:16px;padding-top:15px;padding-bottom:15px;padding-left:15px;padding-right:15px"><strong>🎯 CTA Optimization:</strong> This call-to-action section is strategically placed after building trust and authority. It guides prospects toward the next step in your sales funnel.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"18px","lineHeight":"1.6"},"spacing":{"margin":{"top":"25px"}}}} -->
<p style="margin-top:25px;font-size:18px;line-height:1.6">We're passionate about helping businesses achieve their growth goals. If you're ready to transform your content strategy and drive measurable results, we'd love to discuss how we can help.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"30px"}}}} -->
<div class="wp-block-buttons" style="margin-top:30px"><!-- wp:button {"style":{"color":{"background":"#3498db","text":"#ffffff"},"border":{"radius":"8px"},"spacing":{"padding":{"top":"15px","bottom":"15px","left":"30px","right":"30px"}},"typography":{"fontSize":"18px","fontWeight":"600"}}} -->
<div class="wp-block-button"><a class="wp-block-button__link has-text-color has-background wp-element-button" href="[CUSTOMIZE: Contact Page URL]" style="border-radius:8px;color:#ffffff;background-color:#3498db;font-size:18px;font-weight:600;padding-top:15px;padding-right:30px;padding-bottom:15px;padding-left:30px">Get Your Free Consultation</a></div>
<!-- /wp:button -->

<!-- wp:button {"style":{"color":{"text":"#3498db","background":"#ffffff"},"border":{"radius":"8px","color":"#3498db","width":"2px"},"spacing":{"padding":{"top":"15px","bottom":"15px","left":"30px","right":"30px"}},"typography":{"fontSize":"18px","fontWeight":"600"}}} -->
<div class="wp-block-button"><a class="wp-block-button__link has-text-color has-background has-border-color wp-element-button" href="[CUSTOMIZE: Services Page URL]" style="border-color:#3498db;border-width:2px;border-radius:8px;color:#3498db;background-color:#ffffff;font-size:18px;font-weight:600;padding-top:15px;padding-right:30px;padding-bottom:15px;padding-left:30px">View Our Services</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->

<!-- wp:spacer {"height":"40px"} -->
<div style="height:40px" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->

EOD;
    $template_content .= requestdesk_get_customization_reminder_block();
    $template_content .= <<<'EOD'
EOD;
    return $template_content;
}