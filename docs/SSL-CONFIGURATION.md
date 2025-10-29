# SSL Configuration Guide

WharfTales supports both **HTTP Challenge** and **DNS Challenge** methods for Let's Encrypt SSL certificates.

## Overview

### HTTP Challenge (HTTP-01)
- **Best for**: Public servers with port 80 accessible
- **Requirements**: Port 80 must be open to the internet
- **Limitations**: Cannot issue wildcard certificates
- **Setup**: No additional configuration needed

### DNS Challenge (DNS-01)
- **Best for**: Servers behind firewalls, wildcard certificates
- **Requirements**: DNS provider API credentials
- **Advantages**: Works anywhere, supports wildcards (`*.example.com`)
- **Setup**: Requires DNS provider configuration

---

## HTTP Challenge Setup

### Requirements
1. Domain must point to your server's public IP
2. Port 80 must be accessible from the internet
3. Traefik must be able to respond to `/.well-known/acme-challenge/` requests

### Configuration
1. In the WharfTales GUI, select **Custom Domain**
2. Enable **SSL Certificate**
3. Choose **HTTP Challenge** (default)
4. Deploy your application

### Current Traefik Configuration
```yaml
- "--certificatesresolvers.letsencrypt.acme.httpchallenge=true"
- "--certificatesresolvers.letsencrypt.acme.httpchallenge.entrypoint=web"
```

---

## DNS Challenge Setup

### Supported Providers
- **Cloudflare** ⭐ (Recommended)
- **AWS Route53**
- **DigitalOcean**
- **Google Cloud DNS**
- **Azure DNS**
- **Namecheap**
- **GoDaddy**

### Provider-Specific Setup

#### Cloudflare
1. Log in to [Cloudflare Dashboard](https://dash.cloudflare.com)
2. Go to **My Profile** → **API Tokens**
3. Create token with **Zone:DNS:Edit** permissions
4. In WharfTales:
   - Choose **DNS Challenge**
   - Select **Cloudflare**
   - Enter your Cloudflare email
   - Enter your API key

**Environment Variables:**
```bash
CF_API_EMAIL=your@email.com
CF_API_KEY=your_global_api_key
```

#### AWS Route53
1. Create IAM user with `AmazonRoute53FullAccess` policy
2. Generate Access Key ID and Secret Access Key
3. In WharfTales:
   - Choose **DNS Challenge**
   - Select **AWS Route53**
   - Enter AWS credentials

**Environment Variables:**
```bash
AWS_ACCESS_KEY_ID=AKIAIOSFODNN7EXAMPLE
AWS_SECRET_ACCESS_KEY=wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY
AWS_REGION=us-east-1
```

#### DigitalOcean
1. Go to [API Tokens](https://cloud.digitalocean.com/account/api/tokens)
2. Generate new token with **Write** scope
3. In WharfTales:
   - Choose **DNS Challenge**
   - Select **DigitalOcean**
   - Enter your API token

**Environment Variables:**
```bash
DO_AUTH_TOKEN=dop_v1_your_token_here
```

---

## Updating Traefik for DNS Challenge

### Manual Configuration

To enable DNS challenge globally, update `/opt/wharftales/docker-compose.yml`:

```yaml
services:
  traefik:
    command:
      # Remove HTTP challenge lines:
      # - "--certificatesresolvers.letsencrypt.acme.httpchallenge=true"
      # - "--certificatesresolvers.letsencrypt.acme.httpchallenge.entrypoint=web"
      
      # Add DNS challenge:
      - "--certificatesresolvers.letsencrypt.acme.dnschallenge=true"
      - "--certificatesresolvers.letsencrypt.acme.dnschallenge.provider=cloudflare"
      - "--certificatesresolvers.letsencrypt.acme.dnschallenge.delaybeforecheck=0"
    
    environment:
      # Add provider credentials
      - CF_API_EMAIL=your@email.com
      - CF_API_KEY=your_api_key
```

### Restart Traefik
```bash
cd /opt/wharftales
docker-compose restart traefik
```

---

## Wildcard Certificates

DNS challenge is **required** for wildcard certificates.

### Example
```
Domain: *.example.com
SSL Challenge: DNS
Provider: Cloudflare
```

This will issue a certificate valid for:
- `example.com`
- `*.example.com` (all subdomains)

---

## Troubleshooting

### HTTP Challenge Issues

**Problem**: Certificate not issued
- ✅ Check port 80 is accessible: `curl http://yourdomain.com/.well-known/acme-challenge/test`
- ✅ Verify DNS points to correct IP: `dig yourdomain.com`
- ✅ Check Traefik logs: `docker logs wharftales_traefik`

### DNS Challenge Issues

**Problem**: DNS validation failed
- ✅ Verify API credentials are correct
- ✅ Check DNS provider API is accessible
- ✅ Ensure domain is managed by the DNS provider
- ✅ Check Traefik logs for specific errors

### Common Errors

**"Error while Peeking first byte"**
- Usually timeout issues, not SSL-related
- Check network connectivity

**"Unable to generate a certificate"**
- Verify challenge method configuration
- Check rate limits (Let's Encrypt: 5 failures per hour)
- Ensure domain is publicly resolvable

---

## Security Best Practices

1. **Store credentials securely**
   - Use environment variables
   - Never commit credentials to git
   - Rotate API keys regularly

2. **Limit API permissions**
   - Use minimal required permissions
   - Create separate API keys per service

3. **Monitor certificate expiry**
   - Traefik auto-renews 30 days before expiry
   - Check logs for renewal failures

4. **Use DNS challenge for internal services**
   - Doesn't expose port 80
   - Works behind firewalls/NAT

---

## API Reference

### Create Site with SSL

```json
{
  "name": "My Site",
  "type": "wordpress",
  "domain": "example",
  "domain_suffix": "custom",
  "custom_domain": "example.com",
  "ssl": true,
  "ssl_challenge": "dns",
  "dns_provider": "cloudflare",
  "cf_email": "your@email.com",
  "cf_api_key": "your_api_key"
}
```

### SSL Configuration Object

```json
{
  "challenge": "dns",
  "provider": "cloudflare",
  "credentials": {
    "cf_email": "your@email.com",
    "cf_api_key": "your_api_key"
  }
}
```

---

## Migration Guide

### From HTTP to DNS Challenge

1. Update Traefik configuration in `docker-compose.yml`
2. Add DNS provider environment variables
3. Restart Traefik: `docker-compose restart traefik`
4. Existing certificates will be renewed using new method

### From DNS to HTTP Challenge

1. Remove DNS provider configuration
2. Update Traefik to use HTTP challenge
3. Ensure port 80 is accessible
4. Restart Traefik

---

## Additional Resources

- [Traefik Let's Encrypt Documentation](https://doc.traefik.io/traefik/https/acme/)
- [Let's Encrypt Rate Limits](https://letsencrypt.org/docs/rate-limits/)
- [DNS Provider List](https://doc.traefik.io/traefik/https/acme/#providers)

---

## Support

For issues or questions:
1. Check Traefik logs: `docker logs wharftales_traefik`
2. Verify configuration in Traefik dashboard: `http://your-server:8080`
3. Review this documentation
4. Check Let's Encrypt status: https://letsencrypt.status.io/
